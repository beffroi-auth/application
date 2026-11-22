<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;

/**
 * Called from the notifications lane, so it may be slow; it must not throw for a user it cannot reach at all.
 */
interface PendingApprovalChannelInterface
{
    /**
     * @return bool false when this channel has no way to reach the user, who was told nothing by it
     *
     * @throws NotificationNotDeliveredException when a later attempt may reach the user
     */
    public function tell(User $user, PendingApproval $approval): bool;
}
