<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Routing\RouterEndpointLocator;
use function array_values;
use Beffroi\Oidc\Bridge\Pki\PkiFrameworkCertificateAuthorities;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use function is_string;
use function rtrim;
use function sprintf;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class MutualTlsSection extends ConfigurationSection
{
    public const string ROLE = 'beffroi.mutual_tls.role';

    public const string AUTHORITIES_ROLE = 'beffroi.mutual_tls.authorities_role';

    private const string AUTHORITIES = 'certificate_authorities';

    public function name(): string
    {
        return 'mutual_tls';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('mutual_tls')
            ->info('Where a client that presents a certificate reaches the endpoints (RFC 8705 §5). The certificate itself comes from the edge, which is infrastructure and not configuration (architecture §6); what a deployment says here is the host its listener asks for one on, and the authorities whose certificates tls_client_auth accepts.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('origin')
            ->info('Scheme and authority of that host, without a path ("https://mtls.op.example"). The endpoints are announced under it in "mtls_endpoint_aliases", with the paths they are served at. Absent, the provider serves no mutual TLS: tls_client_auth, self_signed_tls_client_auth and the tokens bound to a certificate are neither announced nor registered. A deployment whose listener sets SSL_CLIENT_CERT on the host of the issuer itself, which the site of SERVER_NAME never does, names that host.')
            ->defaultNull()
            ->end()
            ->append(self::authorities())
            ->end()
            ->end();
    }

    public static function role(?string $origin): RoleSwitch
    {
        return new RoleSwitch(rtrim($origin ?? '', '/') !== '');
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function authoritiesRole(?string $origin, mixed $authorities): RoleSwitch
    {
        return new RoleSwitch(self::role($origin)->on && self::read($authorities) !== []);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function certificateAuthorities(mixed $authorities): PkiFrameworkCertificateAuthorities
    {
        try {
            return new PkiFrameworkCertificateAuthorities(self::read($authorities));
        } catch (InvalidArgumentException $refusal) {
            throw new InvalidConfigurationException(
                sprintf('The "beffroi" configuration key "mutual_tls.%s" is refused: %s', self::AUTHORITIES, $refusal->getMessage()),
                0,
                $refusal,
            );
        }
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $mutualTls = $this->own($config);
        $origin = is_string($mutualTls['origin'] ?? null) ? $mutualTls['origin'] : null;
        $authorities = $mutualTls[self::AUTHORITIES] ?? [];
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$origin]);
        $services
            ->set(self::AUTHORITIES_ROLE, RoleSwitch::class)
            ->factory(self::authoritiesRole(...))
            ->args([$origin, $authorities]);
        $services
            ->get(PkiFrameworkCertificateAuthorities::class)
            ->factory(self::certificateAuthorities(...))
            ->args([$authorities]);
        $services->get(RouterEndpointLocator::class)->arg('$mutualTlsOrigin', $origin);
    }

    /**
     * @throws InvalidConfigurationException
     *
     * @return list<string> in PEM
     */
    private static function read(mixed $authorities): array
    {
        /** @var array<array-key, string> $read */
        $read = self::authorities()->normalized($authorities, 'mutual_tls.' . self::AUTHORITIES);

        return array_values($read);
    }

    private static function authorities(): EnvironmentListDefinition
    {
        $authorities = new EnvironmentListDefinition(self::AUTHORITIES);
        $authorities
            ->info('The certificate authorities whose client certificates tls_client_auth accepts (RFC 8705 §2.1, §7.4), in PEM. Only the leaf reaches the provider, so each one is the authority that signs the client certificates itself, intermediate or root: a certificate one of them signed, valid when it is presented and carrying the name its client registered, authenticates that client, and revocation is not checked. A certificate that is no certificate authority (RFC 5280 §4.2.1.9) is refused. None by default, and then tls_client_auth is neither announced nor registered, while self_signed_tls_client_auth and the tokens bound to a certificate follow "origin" alone.')
            ->scalarPrototype()
            ->cannotBeEmpty()
            ->end()
            ->defaultValue([]);

        return $authorities;
    }
}
