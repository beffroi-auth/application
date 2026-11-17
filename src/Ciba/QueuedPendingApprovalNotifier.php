<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Message\TellUserOfPendingApproval;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;
use Beffroi\Oidc\Port\PendingApprovalNotifierInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueuedPendingApprovalNotifier implements PendingApprovalNotifierInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function tell(PendingApproval $approval): void
    {
        $this->commandBus->dispatch(new TellUserOfPendingApproval($approval));
    }
}
