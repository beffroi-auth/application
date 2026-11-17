<?php

declare(strict_types=1);

namespace App\Http\Account;

use function count;
use App\Controller\AccountConsentsController;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Port\ActiveConsentsInterface;

final readonly class ConsentsSection implements AccountSectionInterface
{
    public function __construct(
        private ActiveConsentsInterface $consents,
    ) {
    }

    public function route(): string
    {
        return AccountConsentsController::ROUTE;
    }

    public function title(): string
    {
        return 'consents.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.consents.state', [
            'count' => count($this->consents->activeOf($user)),
        ]);
    }
}
