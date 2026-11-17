<?php

declare(strict_types=1);

namespace App\Configuration;

use Beffroi\Core\Application\Identity\ClaimHistoryRetention;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class HistorySection extends ConfigurationSection
{
    public const int DEFAULT_RETENTION = JournalSection::DEFAULT_RETENTION;

    public function name(): string
    {
        return 'history';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('history')
            ->info('What a claim of a person used to say, and what they made it say (issue #482). Nothing turns it on; what is configured is how long a change is kept.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('retention')
            ->info('Days a change is kept before the timed step forgets it; the duration the journal keeps an act, a change and the act that announced it being two halves of one answer.')
            ->min(1)
            ->defaultValue(self::DEFAULT_RETENTION)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $services->get(ClaimHistoryRetention::class)->arg('$window', self::days($this->own($config), 'retention'));
    }
}
