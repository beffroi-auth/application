<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Routing\RouterEndpointLocator;
use function is_string;
use function rtrim;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class MutualTlsSection extends ConfigurationSection
{
    public const string ROLE = 'beffroi.mutual_tls.role';

    public function name(): string
    {
        return 'mutual_tls';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('mutual_tls')
            ->info('Where a client that presents a certificate reaches the endpoints (RFC 8705 §5). The certificate itself comes from the edge, which is infrastructure and not configuration (architecture §6); what a deployment says here is the host its listener asks for one on.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('origin')
            ->info('Scheme and authority of that host, without a path ("https://mtls.op.example"). The endpoints are announced under it in "mtls_endpoint_aliases", with the paths they are served at. Absent, the provider serves no mutual TLS: tls_client_auth, self_signed_tls_client_auth and the tokens bound to a certificate are neither announced nor registered. A deployment whose listener sets SSL_CLIENT_CERT on the host of the issuer itself, which the site of SERVER_NAME never does, names that host.')
            ->defaultNull()
            ->end()
            ->end()
            ->end();
    }

    public static function role(?string $origin): RoleSwitch
    {
        return new RoleSwitch(rtrim($origin ?? '', '/') !== '');
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $mutualTls = $this->own($config);
        $origin = is_string($mutualTls['origin'] ?? null) ? $mutualTls['origin'] : null;
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$origin]);
        $services->get(RouterEndpointLocator::class)->arg('$mutualTlsOrigin', $origin);
    }
}
