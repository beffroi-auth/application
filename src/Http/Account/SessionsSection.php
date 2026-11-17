<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountSessionsController;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\AuthenticationSessionRepositoryInterface;
use function count;
use Psr\Clock\ClockInterface;

final readonly class SessionsSection implements AccountSectionInterface
{
    public function __construct(
        private AuthenticationSessionRepositoryInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function route(): string
    {
        return AccountSessionsController::ROUTE;
    }

    public function title(): string
    {
        return 'sessions.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.sessions.state', [
            'count' => count($this->sessions->findActiveByUser($user, $this->clock->now())),
        ]);
    }
}
