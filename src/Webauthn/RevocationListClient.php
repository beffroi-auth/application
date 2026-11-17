<?php

declare(strict_types=1);

namespace App\Webauthn;

use function str_starts_with;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Follows no redirect but one, from FIDO_TEST_SERVICE to FIDO_TEST_SERVICE_HOST, and once: the FIDO test service
 * names its revocation lists on the first host and serves them from the second.
 */
final readonly class RevocationListClient implements HttpClientInterface
{
    public const string FIDO_TEST_SERVICE = 'https://mds3.certinfra.fidoalliance.org/';

    public const string FIDO_TEST_SERVICE_HOST = 'https://mds3.fido.tools/';

    public function __construct(
        private HttpClientInterface $http,
    ) {
    }

    /**
     * @param array<mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = $this->http->request($method, $url, [
            'max_redirects' => 0,
        ] + $options);
        if (! str_starts_with($url, self::FIDO_TEST_SERVICE)) {
            return $response;
        }
        $status = $response->getStatusCode();
        if ($status < 300 || $status >= 400) {
            return $response;
        }
        $location = $response->getHeaders(false)['location'][0] ?? '';
        if (! str_starts_with($location, self::FIDO_TEST_SERVICE_HOST)) {
            return $response;
        }

        return $this->http->request($method, $location, [
            'max_redirects' => 0,
        ] + $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->http->stream($responses, $timeout);
    }

    /**
     * @param array<mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self($this->http->withOptions($options));
    }
}
