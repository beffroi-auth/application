<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\Timeout;
use App\Push\WebPushSender;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class WebPushSection extends ConfigurationSection
{
    public const float DEFAULT_TIMEOUT = 3.0;

    public const string CHANNEL_TAG = 'beffroi.pending_approval_channel';

    public const string ALGORITHM_TAG = 'beffroi.application_server_algorithm';

    public const string ROLE = 'beffroi.web_push.role';

    public function name(): string
    {
        return 'web_push';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('web_push')
            ->info('Reaching a user through the push service of a browser they signed in with (RFC 8030, architecture §5.6). Absent, web push is off: no push leaves this deployment and the page where a browser would subscribe says so.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('subject')
            ->info('The "sub" of the token this provider signs for a push service (RFC 8292 §2.1): a "mailto:" or "https:" URI at which the operator of this deployment can be reached about what it sends. Naming one is what turns web push on, and none turns it off; it may be an environment variable, read when the container builds what web push changes, which "default::" makes none when it is empty.')
            ->defaultNull()
            ->cannotBeEmpty()
            ->end()
            ->floatNode('timeout')
            ->info('Seconds one push to one push service is given, connection included. A push service that hangs must not hold a worker; how many times it is tried again is the retry policy of the notifications lane, as it is for a logout token.')
            ->defaultValue(self::DEFAULT_TIMEOUT)
            ->min(Timeout::MINIMUM)
            ->end()
            ->end()
            ->end();
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function role(?string $subject): RoleSwitch
    {
        if ($subject !== null) {
            WebPushSender::assertSubject($subject);
        }

        return new RoleSwitch($subject !== null);
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $webPush = $this->own($config);
        $subject = is_string($webPush['subject'] ?? null) ? $webPush['subject'] : null;
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$subject]);
        $services->get(WebPushSender::class)->arg('$subject', $subject ?? '')->arg('$timeout', self::timeout($webPush, 'timeout', 'web_push.timeout'));
    }
}
