<?php

declare(strict_types=1);

namespace App\Http\Account;

use function count;
use App\Controller\AccountProfileController;
use Beffroi\Core\Domain\Identity\User;

final readonly class ProfileSection implements AccountSectionInterface
{
    public function route(): string
    {
        return AccountProfileController::ROUTE;
    }

    public function title(): string
    {
        return 'profile.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.profile.state', [
            'count' => count(RecordedInformation::claimsOf($user)),
        ]);
    }
}
