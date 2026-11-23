<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Confirmation\RateLimitedConfirmationSends;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class ConfirmationSection extends ConfigurationSection
{
    /**
     * An address is confirmed with one send, two when a text goes astray: five leave room for a number written wrong
     * and written again.
     */
    public const int DEFAULT_PERSON_LIMIT = 5;

    /**
     * Twice the budget of a person, so that one person never spends it alone.
     */
    public const int DEFAULT_ADDRESS_LIMIT = 10;

    /**
     * A day.
     */
    public const int DEFAULT_INTERVAL = 86400;

    public function name(): string
    {
        return 'confirmation';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('confirmation')
            ->info('The confirmations of an address (decisions 0014 and 0048): a code and a link sent by mail or by SMS to an address nobody verified yet.')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('sends')
            ->info('How many confirmations go out in a window, counted for the person who asks and for the address they go to, whatever the tenant. Past either budget nothing is sent, the page says so, and the refusal is an act of the person. The budgets always exist; these are their numbers.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('person_limit')
            ->info('Confirmations sent for one person in the window.')
            ->defaultValue(self::DEFAULT_PERSON_LIMIT)
            ->min(1)
            ->end()
            ->integerNode('address_limit')
            ->info('Confirmations sent to one address in the window, more than for one person.')
            ->defaultValue(self::DEFAULT_ADDRESS_LIMIT)
            ->min(2)
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
        $sends = self::section($this->own($config), 'sends');
        // The budget itself holds each limit to its floor; only the interval is bounded here.
        $services->get(RateLimitedConfirmationSends::class)->arg('$personLimit', self::integer($sends, 'person_limit'))->arg('$addressLimit', self::integer(
            $sends,
            'address_limit',
        ))->arg('$interval', self::bounded($sends, 'interval', named: 'confirmation.sends.interval'));
    }
}
