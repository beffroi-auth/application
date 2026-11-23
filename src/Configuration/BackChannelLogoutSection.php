<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\LogoutTokenSender;
use App\Http\Timeout;
use App\Messenger\NotificationRetryPolicy;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class BackChannelLogoutSection extends ConfigurationSection
{
    public const float DEFAULT_BACK_CHANNEL_LOGOUT_TIMEOUT = 5.0;

    public const int DEFAULT_BACK_CHANNEL_LOGOUT_RETRIES = 6;

    public const int DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_DELAY = 2;

    public const int DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_MULTIPLIER = 3;

    public const int DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_MAX_DELAY = 60;

    public function name(): string
    {
        return 'back_channel_logout';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('back_channel_logout')
            ->info('How a logout token reaches a client that registered a backchannel_logout_uri (Back-Channel Logout 1.0 §2.5), and for how long. These values are what the transport of the notifications does with a send that failed, a notice to a person aside (notices), the application giving that transport the policy of the provider, and what a signing key is held back for while a logout token it signed may still be on its way (issue #155).')
            ->addDefaultsIfNotSet()
            ->children()
            ->floatNode('timeout')
            ->info('Seconds one attempt is given, connection included. A relying party that hangs must not hold a worker; five seconds is long for a request that reads nothing back, and a client that needs longer is a client that is down, which the retries cover.')
            ->defaultValue(self::DEFAULT_BACK_CHANNEL_LOGOUT_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->integerNode('retries')
            ->info('How many times a send that did not reach its client comes back. A relying party that is restarting answers for a minute and then works; beyond the schedule below, it is down, and the provider says so rather than trying for ever.')
            ->defaultValue(self::DEFAULT_BACK_CHANNEL_LOGOUT_RETRIES)
            ->min(0)
            ->end()
            ->integerNode('retry_delay')
            ->info('Seconds before the first retry.')
            ->defaultValue(self::DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_DELAY)
            ->min(1)
            ->end()
            ->integerNode('retry_multiplier')
            ->info('What each wait is multiplied by to give the next one.')
            ->defaultValue(self::DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_MULTIPLIER)
            ->min(1)
            ->end()
            ->integerNode('retry_max_delay')
            ->info('Seconds a wait never exceeds, whatever the multiplier makes of it.')
            ->defaultValue(self::DEFAULT_BACK_CHANNEL_LOGOUT_RETRY_MAX_DELAY)
            ->min(1)
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $backChannelLogout = $this->own($config);
        $services->get(LogoutTokenSender::class)->arg('$timeout', self::timeout($backChannelLogout, 'timeout', 'back_channel_logout.timeout'));
        $services->get(NotificationRetryPolicy::class)->arg('$retries', self::integer($backChannelLogout, 'retries'))->arg('$delay', self::integer(
            $backChannelLogout,
            'retry_delay',
        ))->arg('$multiplier', self::integer($backChannelLogout, 'retry_multiplier'))->arg('$maxDelay', self::integer($backChannelLogout, 'retry_max_delay'));
    }
}
