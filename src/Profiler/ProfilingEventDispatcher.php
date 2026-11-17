<?php

declare(strict_types=1);

namespace App\Profiler;

use function array_slice;
use function explode;
use Beffroi\Core\Domain\Event\DomainEventInterface;
use function implode;
use Psr\EventDispatcher\EventDispatcherInterface;
use const DATE_ATOM;

final readonly class ProfilingEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $events,
        private OpProfile $profile,
    ) {
    }

    public function dispatch(object $event): object
    {
        $this->profile->record(new Observation('Events', 'Event', [
            'name' => self::nameOf($event),
            'at' => $event instanceof DomainEventInterface ? $event->occurredAt->format(DATE_ATOM) : 'during this request',
        ]));

        return $this->events->dispatch($event);
    }

    private static function nameOf(object $event): string
    {
        $parts = explode('\\', $event::class);

        return implode('', array_slice($parts, -1));
    }
}
