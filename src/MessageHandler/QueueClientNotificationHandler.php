<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendClientNotification;
use Beffroi\Oidc\Domain\Event\BackchannelRequestAnswered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A hop of its own, so that a retry of the call to the client never replays what else listens to an approval.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class QueueClientNotificationHandler
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(BackchannelRequestAnswered $event): void
    {
        $this->commandBus->dispatch(new SendClientNotification(
            $event->tenant,
            $event->clientId,
            $event->notificationEndpoint->value,
            $event->notificationToken,
            $event->authRequestId,
            $event->context,
        ));
    }
}
