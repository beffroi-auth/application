<?php

declare(strict_types=1);

namespace App\Credential;

use App\Message\TellUserOfCredentialChange;
use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Port\CredentialChangeNotifierInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueuedCredentialChangeNotifier implements CredentialChangeNotifierInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function tell(CredentialChange $change): void
    {
        $this->commandBus->dispatch(new TellUserOfCredentialChange($change));
    }
}
