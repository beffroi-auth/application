<?php

declare(strict_types=1);

namespace App\Client;

use Beffroi\Core\Domain\Tenant\Issuer;
use function hash_equals;
use function is_int;
use function is_string;
use function parse_url;
use const PHP_URL_PORT;
use const PHP_URL_SCHEME;
use SensitiveParameter;
use function sprintf;
use function strtolower;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * The application itself, declared to the provider it serves as an ordinary client.
 */
final readonly class SelfClient
{
    /**
     * @throws InvalidConfigurationException when the secret is the secret of the kernel
     */
    public function __construct(
        private UrlGeneratorInterface $router,
        private string $issuer,
        private string $redirectRoute,
        private ?string $postLogoutRoute,
        #[SensitiveParameter]
        private string $secret,
        #[SensitiveParameter]
        string $kernelSecret,
    ) {
        if (hash_equals($kernelSecret, $secret)) {
            throw new InvalidConfigurationException('The "beffroi" configuration key "self_client.secret" is the secret of the kernel: the application authenticates with a secret of its own, which its token requests carry over HTTP.');
        }
    }

    /**
     * @throws InvalidConfigurationException when the router writes no URL for a route this client names
     *
     * @return array<string, mixed> the members of RFC 7591 §2 and the secret of this client
     */
    public function declaration(): array
    {
        $issuer = new Issuer($this->issuer);

        return [
            'secret' => $this->secret,
            'redirect_uris' => [$this->url($issuer, 'redirect_route', $this->redirectRoute)],
            'post_logout_redirect_uris' => $this->postLogoutRoute === null ? [] : [$this->url($issuer, 'post_logout_route', $this->postLogoutRoute)],
            'requires_consent' => false,
        ];
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function url(Issuer $issuer, string $key, string $route): string
    {
        $context = $this->router->getContext();
        $this->router->setContext(self::contextOf($issuer, $context));
        try {
            return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RoutingException $exception) {
            throw new InvalidConfigurationException(
                sprintf(
                    'The "beffroi" configuration key "self_client.%s" names "%s", a route the router writes no URL for: %s',
                    $key,
                    $route,
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        } finally {
            $this->router->setContext($context);
        }
    }

    private static function contextOf(Issuer $issuer, RequestContext $context): RequestContext
    {
        $scheme = parse_url($issuer->value, PHP_URL_SCHEME);
        $scheme = strtolower(is_string($scheme) ? $scheme : 'https');
        $port = parse_url($issuer->value, PHP_URL_PORT);

        return new RequestContext(
            $context->getBaseUrl(),
            'GET',
            $issuer->host,
            $scheme,
            $scheme === 'http' && is_int($port) ? $port : 80,
            $scheme === 'https' && is_int($port) ? $port : 443,
        );
    }
}
