<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Oidc\Domain\Ciba\PendingApproval;

final readonly class TellUserOfPendingApproval implements Notification
{
    public function __construct(
        public PendingApproval $approval,
    ) {
    }
}
