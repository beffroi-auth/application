<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Ciba\PendingApprovalChannelInterface;
use App\Message\TellUserOfPendingApproval;
use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Port\UserRepositoryInterface;
use function implode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every channel tells the user, and the lane comes back only while none of them reached the user: a later attempt
 * tells again every channel, which would write twice to whoever was reached.
 */
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

    /**
     * @throws NotificationNotDeliveredException when no channel reached the user and one of them may reach them later
     */
    public function __invoke(TellUserOfPendingApproval $message): void
    {
        $approval = $message->approval;
        $user = $this->users->find($approval->userId);
        if ($user === null || ! $user->tenant->equals($approval->tenant)) {
            return;
        }
        $reached = false;
        $failures = [];
        foreach ($this->channels as $channel) {
            try {
                $reached = $channel->tell($user, $approval) || $reached;
            } catch (NotificationNotDeliveredException $failure) {
                $failures[] = $failure->getMessage();
            }
        }
        if (! $reached && $failures !== []) {
            throw new NotificationNotDeliveredException(implode(' ', $failures));
        }
    }
}
