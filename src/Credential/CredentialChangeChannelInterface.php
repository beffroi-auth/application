<?php

declare(strict_types=1);

namespace App\Credential;

use App\Messenger\NotificationNotDeliveredException;
use App\Notification\ChannelInterface;
use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Domain\Identity\User;

/**
 * Called from the notifications lane, so it may be slow; it must not throw for a person it cannot reach at all.
 */
interface CredentialChangeChannelInterface extends ChannelInterface
{
    /**
     * @throws NotificationNotDeliveredException when a later attempt may reach the person
     */
    public function tell(User $user, CredentialChange $change): void;
}
