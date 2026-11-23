<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Administration\RateLimitedPersonsBudget;
use Beffroi\Oidc\Application\Administration\AdministeredGroups;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class AdministratorSection extends ConfigurationSection
{
    /**
     * Twelve pages of fifty members in an hour, or a hundred and fifty persons read whole: their account, sessions,
     * journal and consents.
     */
    public const int DEFAULT_LIMIT = 600;

    public const int DEFAULT_INTERVAL = 3600;

    public function name(): string
    {
        return 'administrator';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('administrator')
            ->info('What one administrator of the tenant may read through the administration API (decisions 0038 and 0059). The budget always exists; these are its numbers.')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('persons')
            ->info('How many persons the reads of one administrator deliver in a window, counted by tenant and by administrator: a person read, their sessions, their journal or their consents is one, a page of members one per member, and an answer that changes a person and gives them back is one. A read past it delivers nobody and is answered 429 with Retry-After, and the refusal is an act of the administrator.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('limit')
            ->info('Persons delivered to one administrator in the window, at least the largest page of members.')
            ->defaultValue(self::DEFAULT_LIMIT)
            ->min(AdministeredGroups::LARGEST_PAGE)
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
        $persons = self::section($this->own($config), 'persons');
        // The budget itself holds the limit to the largest page at least; only the interval is bounded here.
        $services->get(RateLimitedPersonsBudget::class)->arg('$limit', self::integer($persons, 'limit'))->arg('$interval', self::bounded(
            $persons,
            'interval',
            named: 'administrator.persons.interval',
        ));
    }
}
