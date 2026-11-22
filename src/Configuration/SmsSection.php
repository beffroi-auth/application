<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Sms\SmsSender;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Notifier\Transport\TransportInterface;

final readonly class SmsSection extends ConfigurationSection
{
    public const string ROLE = 'beffroi.sms.role';

    public const string TRANSPORT = 'beffroi.sms.transport';

    public function name(): string
    {
        return 'sms';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('sms')
            ->info('Reaching a person at the phone number they verified, through the texter of symfony/notifier (decision 0048, architecture §5.6). Absent, SMS is off: no text leaves this deployment.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('dsn')
            ->info('The texter transport of symfony/notifier a text leaves through, the scheme of a bridge the application installs, the number or name it is sent from among its options. Naming one is what turns SMS on, and none turns it off; it may be an environment variable, read when the container builds what SMS changes, which "default::" makes none when it is empty.')
            ->defaultNull()
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end();
    }

    public static function role(?string $dsn): RoleSwitch
    {
        return new RoleSwitch($dsn !== null);
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $sms = $this->own($config);
        $dsn = is_string($sms['dsn'] ?? null) ? $sms['dsn'] : null;
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$dsn]);
        $services
            ->set(self::TRANSPORT, TransportInterface::class)
            ->factory([service('texter.transport_factory'), 'fromString'])
            ->args([$dsn ?? '']);
        $services->get(SmsSender::class)->arg('$transport', service(self::TRANSPORT));
    }
}
