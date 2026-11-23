<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Client\ClientJwksFetcher;
use App\Http\Timeout;
use Beffroi\Oidc\Application\Client\ClientAssertionValidator;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class ClientAuthenticationSection extends ConfigurationSection
{
    public const int DEFAULT_ASSERTION_MAX_LIFETIME = 300;

    public const int DEFAULT_ASSERTION_LEEWAY = 30;

    public const float DEFAULT_JWKS_TIMEOUT = 3.0;

    public const int DEFAULT_JWKS_MAX_SIZE = 65536;

    public const int DEFAULT_JWKS_TTL = 86400;

    public const int DEFAULT_JWKS_REFETCH_COOLDOWN = 300;

    public function name(): string
    {
        return 'client_authentication';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('client_authentication')
            ->info('How a client proves who it is at the endpoints that authenticate one (RFC 6749 §2.3, OIDC Core §9). Which methods the provider takes is the wiring, not this: a method is a tagged service, and what is tagged is what is announced.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('assertion_max_lifetime')
            ->info('Seconds ahead of the request the "exp" of a client assertion may be (RFC 7523 §3). An assertion is minted for one request; a long one is a bearer token nobody watches.')
            ->defaultValue(self::DEFAULT_ASSERTION_MAX_LIFETIME)
            ->min(1)
            ->end()
            ->integerNode('assertion_leeway')
            ->info('Seconds allowed of the clock of a client, both ways.')
            ->defaultValue(self::DEFAULT_ASSERTION_LEEWAY)
            ->min(0)
            ->end()
            ->floatNode('jwks_timeout')
            ->info('Seconds one read of a jwks_uri is given, connection included. A client that hangs must not hold a worker: an endpoint authenticating it is waiting.')
            ->defaultValue(self::DEFAULT_JWKS_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->integerNode('jwks_max_size')
            ->info('Bytes a jwks_uri may answer before the read stops; a JWK Set of a client holds a handful of public keys.')
            ->defaultValue(self::DEFAULT_JWKS_MAX_SIZE)
            ->min(1)
            ->end()
            ->integerNode('jwks_ttl')
            ->info('Seconds the keys read back from a jwks_uri are kept in the pool the workers share.')
            ->defaultValue(self::DEFAULT_JWKS_TTL)
            ->min(1)
            ->end()
            ->integerNode('jwks_refetch_cooldown')
            ->info('Seconds between two reads of the same jwks_uri, after a read that failed and when an assertion names a "kid" the kept set does not hold. A client that rotates a key is followed within one request; neither a key that was never published nor a jwks_uri that fails or hangs makes the provider call it on every request.')
            ->defaultValue(self::DEFAULT_JWKS_REFETCH_COOLDOWN)
            ->min(0)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $clientAuthentication = $this->own($config);
        $services->get(ClientAssertionValidator::class)->arg('$maximumLifetime', self::assertionLifetime($clientAuthentication))->arg(
            '$leeway',
            self::assertionLeeway($clientAuthentication),
        );
        $services->get('beffroi.oidc.client_keys.provider')->arg('$cooldown', self::refetchCooldown($clientAuthentication));
        $services->get(ClientJwksFetcher::class)->arg('$timeout', self::timeout(
            $clientAuthentication,
            'jwks_timeout',
            'client_authentication.jwks_timeout',
        ))->arg('$maximumSize', self::bytes($clientAuthentication, 'jwks_max_size', 'client_authentication.jwks_max_size'));
        $services->get('beffroi.oidc.client_keys.cache')->arg('$lifetime', self::keptFor($clientAuthentication));
    }

    /**
     * @param array<array-key, mixed> $clientAuthentication
     */
    public static function assertionLifetime(array $clientAuthentication): object
    {
        return self::bounded($clientAuthentication, 'assertion_max_lifetime', named: 'client_authentication.assertion_max_lifetime');
    }

    /**
     * @param array<array-key, mixed> $clientAuthentication
     */
    public static function assertionLeeway(array $clientAuthentication): object
    {
        return self::bounded($clientAuthentication, 'assertion_leeway', named: 'client_authentication.assertion_leeway', minimum: 0);
    }

    /**
     * @param array<array-key, mixed> $clientAuthentication
     */
    public static function refetchCooldown(array $clientAuthentication): object
    {
        return self::bounded($clientAuthentication, 'jwks_refetch_cooldown', named: 'client_authentication.jwks_refetch_cooldown', minimum: 0);
    }

    /**
     * @param array<array-key, mixed> $clientAuthentication
     */
    public static function keptFor(array $clientAuthentication): object
    {
        return self::bounded($clientAuthentication, 'jwks_ttl', named: 'client_authentication.jwks_ttl');
    }
}
