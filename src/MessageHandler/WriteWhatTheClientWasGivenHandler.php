<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Beffroi\Oidc\Application\Disclosure\DisclosureRecording;
use Beffroi\Oidc\Domain\Event\TokensIssued;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class WriteWhatTheClientWasGivenHandler
{
    public function __construct(
        private DisclosureRecording $recording,
    ) {
    }

    public function __invoke(TokensIssued $event): void
    {
        $this->recording->record($event);
    }
}
