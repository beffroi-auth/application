<?php

declare(strict_types=1);

namespace App\Configuration;

use Beffroi\Oidc\Application\Trust\IssuerAssertionValidator;
use Beffroi\Oidc\Bridge\Configuration\ConfigurationTrustedIssuers;
use Beffroi\Core\Port\SignatureAlgorithmsInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class TrustedIssuersSection extends ConfigurationSection
{
    public const int DEFAULT_ASSERTION_MAX_LIFETIME = 300;

    public const int DEFAULT_ASSERTION_LEEWAY = 30;

    public function name(): string
    {
        return 'trusted_issuers';
    }

    public function describe(NodeBuilder $children): void
    {
        $children->append(self::issuers());
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function trustedIssuers(string $tenantSlug, mixed $definitions, SignatureAlgorithmsInterface $algorithms): ConfigurationTrustedIssuers
    {
        /** @var array<string, array<string, mixed>> $issuers */
        $issuers = self::issuers()->normalized($definitions, 'trusted_issuers');

        return new ConfigurationTrustedIssuers($tenantSlug, $issuers, $algorithms);
    }

    private static function issuers(): EnvironmentListDefinition
    {
        $issuers = new EnvironmentListDefinition('trusted_issuers');
        $issuers
            ->info('The issuers whose assertions this tenant accepts as an authorization (RFC 7523 §2.1), keyed by the "iss" they sign with. Each one is a closed declaration: nothing about it is inferred, and an issuer nobody declared is unknown.')
            ->useAttributeAsKey('issuer')
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->children()
            ->variableNode('jwks')
            ->info('The JWK Set of the issuer, inline (RFC 7517 §5): public keys only, and the only keys an assertion of this issuer is ever verified against, whatever its header names (RFC 8725 §3.10). Either this or "jwks_uri", never both and never neither.')
            ->defaultNull()
            ->end()
            ->scalarNode('jwks_uri')
            ->info('Where the JWK Set of the issuer is read back from instead (RFC 7517 §5), which is how an issuer that rotates its keys publishes them: one GET over HTTPS, no redirect followed, read once and kept in a pool the workers share, and read again at most once per cooldown when an assertion names a "kid" the kept set does not hold. Either this or "jwks", never both and never neither.')
            ->defaultNull()
            ->end()
            ->arrayNode('signing_algorithms')
            ->info('The JWS algorithms an assertion of this issuer may be signed with (RFC 7515 §4.1.1), among those the provider is wired with; at least one. A name nothing verifies fails at boot.')
            ->scalarPrototype()
            ->end()
            ->end()
            ->arrayNode('clients')
            ->info('The clients that may present an assertion of this issuer at the token endpoint, by client identifier; at least one. An issuer trusted for a gateway is not thereby trusted for every client of the tenant.')
            ->scalarPrototype()
            ->end()
            ->end()
            ->scalarNode('identity_provider')
            ->info('The source of the linked identities the subjects of its assertions name (decision 0002): the "sub" of an assertion is a user of this tenant only when a linked identity of that source says so, and no user is ever created here.')
            ->defaultNull()
            ->end()
            ->arrayNode('trusted_scopes')
            ->info('What this issuer is trusted for: the scopes of a response are a subset of these, of what the client registered and of what the request asked for. "openid" among them buys nothing, the grant refusing it: it authenticates nobody.')
            ->scalarPrototype()
            ->end()
            ->end()
            ->end()
            ->end();

        return $issuers;
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $services->get(ConfigurationTrustedIssuers::class)->factory(self::trustedIssuers(...))->arg('$tenantSlug', self::string(
            self::section($config, 'tenant'),
            'slug',
        ))->arg('$definitions', $config['trusted_issuers'] ?? []);
        $clientAuthentication = self::section($config, 'client_authentication');
        $services->get(IssuerAssertionValidator::class)->arg('$maximumLifetime', ClientAuthenticationSection::assertionLifetime($clientAuthentication))->arg(
            '$leeway',
            ClientAuthenticationSection::assertionLeeway($clientAuthentication),
        );
        $services->get('beffroi.oidc.issuer_keys.provider')->arg('$cooldown', ClientAuthenticationSection::refetchCooldown($clientAuthentication));
        $services->get('beffroi.oidc.issuer_keys.cache')->arg('$lifetime', ClientAuthenticationSection::keptFor($clientAuthentication));
    }
}
