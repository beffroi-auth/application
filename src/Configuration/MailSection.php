<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Mail\MailSender;
use App\Mail\MailSenderAddress;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use Symfony\Component\Mailer\Transport\TransportInterface;

final readonly class MailSection extends ConfigurationSection
{
    public const string ROLE = 'beffroi.mail.role';

    public const string TRANSPORT = 'beffroi.mail.transport';

    public function name(): string
    {
        return 'mail';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('mail')
            ->info('Reaching a person at the email address marked verified, through symfony/mailer (decision 0048, architecture §5.6). Absent, mail is off: no mail leaves this deployment.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('dsn')
            ->info('The transport of symfony/mailer a mail leaves through: "smtp://user:password@host:port", which the component carries, or the scheme of a bridge the application installs. Naming one is what turns mail on, and none turns it off; it may be an environment variable, read when the container builds what mail changes, which "default::" makes none when it is empty. A DSN that no installed factory reads writes no mail, and the log says why at each one.')
            ->defaultNull()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('sender')
            ->info('The address a mail is written from, alone or behind a name ("Acme <no-reply@acme.example>"). A DSN without a sender, or with a sender that is no address, writes no mail, and the log says why at each one.')
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
        $mail = $this->own($config);
        $dsn = is_string($mail['dsn'] ?? null) ? $mail['dsn'] : null;
        $sender = is_string($mail['sender'] ?? null) ? $mail['sender'] : null;
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$dsn]);
        $services
            ->set(self::TRANSPORT, TransportInterface::class)
            ->factory([service('mailer.transport_factory'), 'fromString'])
            ->args([$dsn ?? '']);
        $services->get(MailSender::class)->arg('$transport', service_closure(self::TRANSPORT))->arg('$sender', $sender);
        $services->get(MailSenderAddress::class)->arg('$sender', $sender);
    }
}
