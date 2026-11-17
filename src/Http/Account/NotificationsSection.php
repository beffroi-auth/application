<?php

declare(strict_types=1);

namespace App\Http\Account;

use function count;
use App\Controller\AccountNotificationsController;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;

final readonly class NotificationsSection implements AccountSectionInterface
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    public function route(): string
    {
        return AccountNotificationsController::ROUTE;
    }

    public function title(): string
    {
        return 'notifications.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.notifications.state', [
            'count' => count($this->subscriptions->findActiveByUser($user)),
        ]);
    }
}
