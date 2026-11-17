<?php

declare(strict_types=1);

namespace App\Configuration;

use Beffroi\Core\Application\Journal\JournalRetention;
use Beffroi\Oidc\Application\Disclosure\DisclosureRetention;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class JournalSection extends ConfigurationSection
{
    public const int DEFAULT_RETENTION = 180;

    public function name(): string
    {
        return 'journal';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('journal')
            ->info('The acts of the provider, written down and forgotten again (issue #454): who did what, when and from where. Nothing turns it on; what is configured is how long an act is kept.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('retention')
            ->info('Days an act is kept before the timed step forgets it; six months by default, the duration the deliberation 2021-122 of the CNIL holds for a log of connections. A deployment under another obligation says its own.')
            ->min(1)
            ->defaultValue(self::DEFAULT_RETENTION)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $own = $this->own($config);
        $services->get(JournalRetention::class)->arg('$window', self::days($own, 'retention'));
        $services->get(DisclosureRetention::class)->arg('$window', self::days($own, 'retention'));
    }
}
