<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Geo\GeoLite2Databases;
use App\Geo\GeoLite2Refresh;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class GeoipSection extends ConfigurationSection
{
    public const string LICENSE_KEY_ENVIRONMENT_VARIABLE = 'GEOIP_LICENSE_KEY';

    public function name(): string
    {
        return 'geoip';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('geoip')
            ->info('The databases of addresses the enrichment of a provenance reads (architecture §3.5): where they are kept and what they are downloaded under.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('storage')
            ->info('Directory of the GeoLite2 databases the enrichment reads, next to the keys: the files "beffroi:geoip:refresh" and the weekly step store, empty until one of them ran, which every flow of the provider is served without.')
            ->defaultValue('%kernel.project_dir%/var/geoip')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('license_key')
            ->info('Licence key of the MaxMind account the GeoLite2 editions are downloaded under; empty by default, and a deployment that leaves it empty downloads nothing and says no place. The databases are published by MaxMind under their own licence, which is why nothing is shipped and nothing is fetched without this.')
            ->defaultValue('')
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $own = $this->own($config);
        $services->get(GeoLite2Databases::class)->arg('$directory', self::string($own, 'storage'));
        $services->get(GeoLite2Refresh::class)->arg('$licenseKey', self::string($own, 'license_key'));
    }
}
