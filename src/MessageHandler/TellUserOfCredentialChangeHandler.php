<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Credential\CredentialChangeChannels;
use App\Message\TellUserOfCredentialChange;
use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TellUserOfCredentialChangeHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private CredentialChangeChannels $channels,
    ) {
    }

    /**
     * @throws NotificationNotDeliveredException when the channel of the notice did not reach the person this time
     */
    public function __invoke(TellUserOfCredentialChange $message): void
    {
        $channel = $this->channels->named($message->channel);
        $change = $message->change;
        $user = $this->users->find($change->userId);
        if ($channel === null || $user === null || ! $user->belongsTo($change->tenant)) {
            return;
        }
        $channel->tell($user, $change);
    }
}
