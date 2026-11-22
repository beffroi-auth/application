<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Mail\MailSender;
use function is_string;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

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
            ->info('Reaching a person at the email address they verified, through symfony/mailer (decision 0048, architecture §5.6). Absent, mail is off: no mail leaves this deployment.')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('dsn')
            ->info('The transport of symfony/mailer a mail leaves through: "smtp://user:password@host:port", which the component carries, or the scheme of a bridge the application installs. Naming one is what turns mail on, and none turns it off; it may be an environment variable, read when the container builds what mail changes, which "default::" makes none when it is empty.')
            ->defaultNull()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('sender')
            ->info('The address a mail is written from, alone or behind a name ("Acme <no-reply@acme.example>"), which a DSN cannot go without.')
            ->defaultNull()
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end();
    }

    /**
     * @throws InvalidConfigurationException when a DSN is named without a sender, or with a sender that is no address
     */
    public static function role(?string $dsn, ?string $sender): RoleSwitch
    {
        if ($dsn !== null) {
            self::sender($sender);
        }

        return new RoleSwitch($dsn !== null);
    }

    /**
     * RFC 5322 §3.4
     *
     * @throws InvalidConfigurationException
     */
    public static function sender(?string $sender): Address
    {
        if ($sender === null) {
            throw new InvalidConfigurationException('The "beffroi" configuration key "mail.sender" names the address the mail of this deployment is written from, and none is named.');
        }
        try {
            return Address::create($sender);
        } catch (MimeException) {
            throw new InvalidConfigurationException('The "beffroi" configuration key "mail.sender" is not an address, alone or behind a name.');
        }
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $mail = $this->own($config);
        $dsn = is_string($mail['dsn'] ?? null) ? $mail['dsn'] : null;
        $sender = is_string($mail['sender'] ?? null) ? $mail['sender'] : null;
        $services
            ->set(self::ROLE, RoleSwitch::class)
            ->factory(self::role(...))
            ->args([$dsn, $sender]);
        $services
            ->set(self::TRANSPORT, TransportInterface::class)
            ->factory([service('mailer.transport_factory'), 'fromString'])
            ->args([$dsn ?? '']);
        $services->get(MailSender::class)->arg('$transport', service(self::TRANSPORT))->arg(
            '$sender',
            inline_service(Address::class)->factory(self::sender(...))->args([$sender]),
        );
    }
}
