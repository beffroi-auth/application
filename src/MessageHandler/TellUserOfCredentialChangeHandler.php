<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Credential\CredentialChangeChannelInterface;
use App\Message\TellUserOfCredentialChange;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TellUserOfCredentialChangeHandler
{
    /**
     * @param iterable<CredentialChangeChannelInterface> $channels
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private iterable $channels,
    ) {
    }

    public function __invoke(TellUserOfCredentialChange $message): void
    {
        $change = $message->change;
        $user = $this->users->find($change->userId);
        if ($user === null || ! $user->belongsTo($change->tenant)) {
            return;
        }
        foreach ($this->channels as $channel) {
            $channel->tell($user, $change);
        }
    }
}
