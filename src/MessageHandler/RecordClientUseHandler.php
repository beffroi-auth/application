<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Beffroi\Oidc\Application\Client\Registration\ClientUseRecording;
use Beffroi\Oidc\Domain\Event\ClientInUseInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class RecordClientUseHandler
{
    public function __construct(
        private ClientUseRecording $recording,
    ) {
    }

    public function __invoke(ClientInUseInterface $event): void
    {
        $this->recording->record($event);
    }
}
