<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Ciba\PendingApprovalChannelInterface;
use App\Message\TellUserOfPendingApproval;
use Beffroi\Core\Port\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TellUserOfPendingApprovalHandler
{
    /**
     * @param iterable<PendingApprovalChannelInterface> $channels
     */
    public function __construct(
        private UserRepositoryInterface $users,
        private iterable $channels,
    ) {
    }

    public function __invoke(TellUserOfPendingApproval $message): void
    {
        $approval = $message->approval;
        $user = $this->users->find($approval->userId);
        if ($user === null || ! $user->tenant->equals($approval->tenant)) {
            return;
        }
        foreach ($this->channels as $channel) {
            $channel->tell($user, $approval);
        }
    }
}
