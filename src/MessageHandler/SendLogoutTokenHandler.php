<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Http\LogoutTokenSender;
use App\Message\SendLogoutToken;
use App\Messenger\NotificationNotDeliveredException;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Back-Channel Logout 1.0 §2.8
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendLogoutTokenHandler
{
    public function __construct(
        private LogoutTokenSender $sender,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendLogoutToken $message): void
    {
        $delivery = $this->sender->send($message->logoutUri, $message->logoutToken);
        if ($delivery->delivered) {
            return;
        }
        if (! $delivery->final) {
            throw new NotificationNotDeliveredException($delivery->detail);
        }
        $this->events->dispatch($message->givenUp($this->clock->now(), $delivery->detail));
    }
}
