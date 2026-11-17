<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\Command;
use App\Message\Event;
use App\Message\Notification;
use App\Message\Query;
use App\Message\SyncMessage;
use Beffroi\Core\Domain\Event\DomainEventInterface;

/**
 * The Messenger configuration the provider prepends: buses, middleware, transports and routing.
 */
final readonly class MessengerContract
{
    public const string DEFAULT_DSN = 'doctrine://default?queue_name=' . LaneDsn::PLACEHOLDER;

    public const string DSN_ENVIRONMENT_VARIABLE = 'BEFFROI_MESSENGER_DSN';

    /**
     * Always the database: a failure transport on the broker that failed is one nobody reads.
     */
    public const string FAILURE_DSN = 'doctrine://default?queue_name=failed';

    public const string DEFAULT_BUS = 'command.bus';

    public const string FAILURE_TRANSPORT = 'failed';

    public const string SERIALIZER = 'beffroi.messenger.serializer';

    /**
     * Services of the provider, not DoctrineBundle's `doctrine_ping_connection` and `doctrine_close_connection`:
     * up to 3.3.4, DoctrineBundle wires the classes symfony/doctrine-bridge 8.2 deprecates.
     */
    public const string PING_CONNECTION = 'beffroi.messenger.ping_connection';

    public const string CLOSE_CONNECTION = 'beffroi.messenger.close_connection';

    /**
     * @var list<string>
     */
    public const array LANES = ['async_priority_high', 'async_priority_medium', 'async_priority_low', 'async_notifications'];

    /**
     * `dispatch_after_current_bus` is left out: the default middleware adds it, and naming it again runs it twice.
     *
     * @var list<string>
     */
    public const array COMMAND_MIDDLEWARE = [
        'router_context',
        self::PING_CONNECTION,
        self::CLOSE_CONNECTION,
        'doctrine_transaction',
    ];

    /**
     * Messenger sends a message to every transport its class, parents and interfaces are routed to: a class carrying
     * two markers is sent twice.
     *
     * @return array<class-string, string>
     */
    public static function routing(): array
    {
        return [
            Command::class => 'async_priority_medium',
            Event::class => 'async_priority_low',
            DomainEventInterface::class => 'async_priority_low',
            Notification::class => 'async_notifications',
            Query::class => 'sync',
            SyncMessage::class => 'sync',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function configuration(): array
    {
        $transports = ['sync' => 'sync://'];
        foreach (self::LANES as $lane) {
            $transports[$lane] = [
                'dsn' => LaneDsn::of($lane, self::DSN_ENVIRONMENT_VARIABLE),
                // The retirement of a signing key reads the same policy for how long a logout token travels.
                'retry_strategy' => $lane === 'async_notifications'
                    ? ['service' => NotificationRetryPolicy::class]
                    : ['max_retries' => 4, 'delay' => 1000, 'max_delay' => 30_000, 'multiplier' => 4],
            ];
        }
        $transports[self::FAILURE_TRANSPORT] = self::FAILURE_DSN;

        return [
            'default_bus' => self::DEFAULT_BUS,
            'failure_transport' => self::FAILURE_TRANSPORT,
            'serializer' => [
                'default_serializer' => self::SERIALIZER,
            ],
            'transports' => $transports,
            'routing' => self::routing(),
            'buses' => [
                'query.bus' => null,
                'command.bus' => [
                    'middleware' => self::COMMAND_MIDDLEWARE,
                ],
                'event.bus' => [
                    'default_middleware' => 'allow_no_handlers',
                ],
            ],
        ];
    }
}
