<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Confirmation\AddressConfirmationChannelInterface;
use App\Message\SendAddressConfirmation;
use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendAddressConfirmationHandler
{
    /**
     * @param iterable<AddressConfirmationChannelInterface> $channels
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private iterable $channels,
    ) {
    }

    /**
     * @throws NotificationNotDeliveredException when the channel of the address did not reach it this time
     */
    public function __invoke(SendAddressConfirmation $message): void
    {
        $user = $this->users->find($message->userId);
        if ($user === null || ! $user->belongsTo($message->tenant)) {
            return;
        }
        foreach ($this->channels as $channel) {
            if ($channel->claim() === $message->claim) {
                $channel->send($user, $message);
            }
        }
    }
}
