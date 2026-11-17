<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountArchiveController;
use App\Http\ArchiveState;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\PersonalArchiveRepositoryInterface;
use Psr\Clock\ClockInterface;

final readonly class ArchiveSection implements AccountSectionInterface
{
    public function __construct(
        private PersonalArchiveRepositoryInterface $archives,
        private ClockInterface $clock,
    ) {
    }

    public function route(): string
    {
        return AccountArchiveController::ROUTE;
    }

    public function title(): string
    {
        return 'archive.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.archive.' . ArchiveState::of($this->archives->latestOf($user), $this->clock->now()));
    }
}
