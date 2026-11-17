<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountTwoFactorController;
use Beffroi\Core\Application\Totp\ActiveTotp;
use Beffroi\Core\Domain\Identity\User;

final readonly class TwoFactorSection implements AccountSectionInterface
{
    public function __construct(
        private ActiveTotp $activeTotp,
    ) {
    }

    public function route(): string
    {
        return AccountTwoFactorController::ROUTE;
    }

    public function title(): string
    {
        return 'two_factor.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState(
            $this->activeTotp->of($user) === null
                ? 'account.two_factor.state.none'
                : 'account.two_factor.state.active',
        );
    }
}
