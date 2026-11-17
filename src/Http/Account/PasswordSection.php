<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountPasswordController;
use Beffroi\Core\Application\Credential\ActivePassword;
use Beffroi\Core\Domain\Identity\User;
use Psr\Clock\ClockInterface;

final readonly class PasswordSection implements AccountSectionInterface
{
    public function __construct(
        private ActivePassword $activePassword,
        private ClockInterface $clock,
    ) {
    }

    public function route(): string
    {
        return AccountPasswordController::ROUTE;
    }

    public function title(): string
    {
        return 'password.title';
    }

    public function state(User $user): AccountSectionState
    {
        $password = $this->activePassword->of($user);

        if ($password === null) {
            return new AccountSectionState('account.password.none');
        }
        if ($password->isBreached()) {
            return new AccountSectionState('account.password.breached');
        }

        return new AccountSectionState('account.password.state', [
            'days' => (int) $this->clock->now()->diff($password->hashChangedAt)->days,
        ]);
    }
}
