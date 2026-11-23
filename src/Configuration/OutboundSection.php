<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\OutboundGuardInterface;
use App\Http\PrivateNetworkGuard;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * Decision 0006
 */
final readonly class OutboundSection extends ConfigurationSection
{
    public function name(): string
    {
        return 'outbound';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('outbound')
            ->info('The requests whose URL a client, a registrant or a person chooses: a jwks_uri, a sector_identifier_uri, a request_uri, a back-channel logout URI, a CIBA notification endpoint, the endpoint of a push subscription.')
            ->addDefaultsIfNotSet()
            ->append(self::reachablePrivateNetworks())
            ->end();
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function guard(mixed $reachable): PrivateNetworkGuard
    {
        $networks = [];
        foreach (self::reachablePrivateNetworks()->normalized($reachable, 'outbound.reachable_private_networks') as $network) {
            if (! is_string($network)) {
                throw new InvalidConfigurationException('The "beffroi" configuration key "outbound.reachable_private_networks" must be a list of strings.');
            }
            $networks[] = $network;
        }

        return new PrivateNetworkGuard($networks);
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $services
            ->set(PrivateNetworkGuard::class)
            ->factory(self::guard(...))
            ->args([$this->own($config)['reachable_private_networks'] ?? []]);
        $services->alias(OutboundGuardInterface::class, PrivateNetworkGuard::class);
    }

    private static function reachablePrivateNetworks(): EnvironmentListDefinition
    {
        $networks = new EnvironmentListDefinition('reachable_private_networks');
        $networks
            ->info('The networks of the deployment such a request may reach, IP addresses or networks in CIDR notation. Every other private, loopback, link-local or reserved address is refused, checked once the name is resolved and on every address the request connects to (decision 0006). Empty, the default, reaches none: a deployment whose clients answer on its own networks names those networks and no wider.')
            ->scalarPrototype()
            ->cannotBeEmpty()
            ->end()
            ->defaultValue([]);

        return $networks;
    }
}
