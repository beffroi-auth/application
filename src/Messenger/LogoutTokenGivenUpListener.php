<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\SendLogoutToken;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Oidc\Domain\Event\LogoutTokenGivenUp;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final readonly class LogoutTokenGivenUpListener
{
    public function __construct(
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        if (! $message instanceof SendLogoutToken) {
            return;
        }
        $this->events->dispatch(
            new LogoutTokenGivenUp(
                $this->clock->now(),
                $message->tenant,
                $message->context,
                $message->clientId,
                $message->subject,
                $message->sessionId,
                ExceptionTrail::of($event->getThrowable()),
            ),
        );
    }
}
