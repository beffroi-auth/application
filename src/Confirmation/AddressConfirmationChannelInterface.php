<?php

declare(strict_types=1);

namespace App\Confirmation;

use App\Message\SendAddressConfirmation;
use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Domain\Identity\User;

/**
 * Called from the notifications lane, so it may be slow; it must not throw for a person it cannot reach at all.
 */
interface AddressConfirmationChannelInterface
{
    /**
     * OIDC Core §5.1, the claim of the addresses it reaches.
     */
    public function claim(): string;

    /**
     * @throws NotificationNotDeliveredException when a later attempt may reach the address
     */
    public function send(User $user, SendAddressConfirmation $confirmation): void;
}
