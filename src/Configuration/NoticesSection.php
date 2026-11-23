<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Messenger\NotificationRetryPolicy;
use App\Messenger\RetrySchedule;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

final readonly class NoticesSection extends ConfigurationSection
{
    public const string NAME = 'notices';

    public const int DEFAULT_RETRIES = 30;

    public const int DEFAULT_RETRY_DELAY = 60;

    public const int DEFAULT_RETRY_MULTIPLIER = 2;

    public const int DEFAULT_RETRY_MAX_DELAY = 3600;

    public function name(): string
    {
        return self::NAME;
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode(self::NAME)
            ->info('How long a notice of a change to the credentials of an account is tried again on a channel that did not take it (decision 0049): a mail server or an SMS provider that is down for hours. Once the schedule is spent, the notice not delivered is an act of the person. By default, 31 attempts over 25 hours.')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('retries')
            ->info('How many times a notice that did not reach the person comes back.')
            ->defaultValue(self::DEFAULT_RETRIES)
            ->min(0)
            ->end()
            ->integerNode('retry_delay')
            ->info('Seconds before the first retry.')
            ->defaultValue(self::DEFAULT_RETRY_DELAY)
            ->min(1)
            ->end()
            ->integerNode('retry_multiplier')
            ->info('What each wait is multiplied by to give the next one.')
            ->defaultValue(self::DEFAULT_RETRY_MULTIPLIER)
            ->min(1)
            ->end()
            ->integerNode('retry_max_delay')
            ->info('Seconds a wait never exceeds, whatever the multiplier makes of it.')
            ->defaultValue(self::DEFAULT_RETRY_MAX_DELAY)
            ->min(1)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $notices = $this->own($config);
        $services->get(NotificationRetryPolicy::class)->arg('$notices', inline_service(RetrySchedule::class)->args([
            self::integer($notices, 'retries'),
            self::integer($notices, 'retry_delay'),
            self::integer($notices, 'retry_multiplier'),
            self::integer($notices, 'retry_max_delay'),
            self::NAME,
        ]));
    }
}
