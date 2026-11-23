<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\GivenUpAsAnAct;
use Beffroi\Core\Exception\ExceptionTrail;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final readonly class GivenUpListener
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
        if (! $message instanceof GivenUpAsAnAct) {
            return;
        }
        $this->events->dispatch($message->givenUp($this->clock->now(), ExceptionTrail::of($event->getThrowable())));
    }
}
