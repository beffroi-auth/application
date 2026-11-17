<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Routing\RouterEndpointLocator;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class MutualTlsSection extends ConfigurationSection
{
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
            ->info('Scheme and authority of that host, without a path ("https://mtls.op.example"). The endpoints are announced under it in "mtls_endpoint_aliases", with the paths they are served at. Absent, the document announces no alias and a client authenticating by mutual TLS reaches the ordinary endpoints, which is what a deployment serving one host does.')
            ->defaultNull()
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $mutualTls = $this->own($config);
        $services->get(RouterEndpointLocator::class)->arg('$mutualTlsOrigin', is_string($mutualTls['origin'] ?? null) ? $mutualTls['origin'] : null);
    }
}
