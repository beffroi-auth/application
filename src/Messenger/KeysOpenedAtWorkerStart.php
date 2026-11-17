<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Key\KeysOpenedAtStart;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

final readonly class KeysOpenedAtWorkerStart
{
    public function __construct(
        private KeysOpenedAtStart $keys,
    ) {
    }

    #[AsEventListener(event: WorkerStartedEvent::class)]
    public function __invoke(): void
    {
        $this->keys->open();
    }
}
