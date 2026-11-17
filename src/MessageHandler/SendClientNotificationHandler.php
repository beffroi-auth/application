<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Http\ClientNotificationSender;
use App\Message\SendClientNotification;
use App\Messenger\NotificationNotDeliveredException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CIBA Core §10.2
 *
 * A refusal is never retried: the notification carries a credential of the client that has just rejected it.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendClientNotificationHandler
{
    public function __construct(
        private ClientNotificationSender $sender,
    ) {
    }

    public function __invoke(SendClientNotification $message): void
    {
        $delivery = $this->sender->send($message->notificationEndpoint, $message->notificationToken, $message->authRequestId);
        if ($delivery->delivered || $delivery->final) {
            return;
        }

        throw new NotificationNotDeliveredException($delivery->detail);
    }
}
