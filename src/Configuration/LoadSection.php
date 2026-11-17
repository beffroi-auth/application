<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Load\LoadBudget;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class LoadSection extends ConfigurationSection
{
    /**
     * Three times the 817 requests a minute the functional suite sends from one address at its busiest.
     */
    public const int DEFAULT_ORIGIN_LIMIT = 2400;

    /**
     * Half the budget of an origin, so that a looping client is refused before the address it calls from is.
     */
    public const int DEFAULT_CLIENT_LIMIT = 1200;

    public const int DEFAULT_INTERVAL = 60;

    public function name(): string
    {
        return 'load';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('load')
            ->info('How many requests the provider takes in a window, by origin and by authenticated client (decision 0014). Both budgets always exist; these are their numbers, which depend on the size of the deployment. A number too low on /token or on /jwks is an outage a deployment inflicts on itself, so they are measured on what its own clients send.')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('origin')
            ->info('What one client address may send, whatever it asks for and whether or not it has proved anything. It is not scoped to a tenant: what it bounds is the load on the machine, which every tenant of a deployment shares.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')
            ->info('Requests taken from one address in the window.')
            ->defaultValue(self::DEFAULT_ORIGIN_LIMIT)
            ->min(1)
            ->end()
            ->integerNode('interval')
            ->info('Seconds the window covers.')
            ->defaultValue(self::DEFAULT_INTERVAL)
            ->min(1)
            ->end()
            ->end()
            ->end()
            ->arrayNode('client')
            ->info('What one client may ask once its authentication has succeeded, by tenant and by client. This is the one a tenant reads when one of its applications goes into a loop.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')
            ->info('Requests taken from one authenticated client in the window.')
            ->defaultValue(self::DEFAULT_CLIENT_LIMIT)
            ->min(1)
            ->end()
            ->integerNode('interval')
            ->info('Seconds the window covers.')
            ->defaultValue(self::DEFAULT_INTERVAL)
            ->min(1)
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $load = $this->own($config);
        foreach ([LoadBudget::ORIGIN, LoadBudget::CLIENT] as $budget) {
            $numbers = self::section($load, $budget);
            // The budget itself holds the limit to one request at least; only the interval is bounded here.
            $services->get('beffroi.load.' . $budget)->arg('$limit', self::integer($numbers, 'limit'))->arg('$interval', self::bounded(
                $numbers,
                'interval',
                named: 'load.' . $budget . '.interval',
            ));
        }
    }
}
