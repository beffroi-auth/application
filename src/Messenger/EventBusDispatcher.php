<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class EventBusDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function dispatch(object $event): object
    {
        $this->eventBus->dispatch(Envelope::wrap($event, [new DispatchAfterCurrentBusStamp()]));

        return $event;
    }
}
