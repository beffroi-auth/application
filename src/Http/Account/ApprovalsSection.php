<?php

declare(strict_types=1);

namespace App\Http\Account;

use function count;
use App\Controller\PendingApprovalsController;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Application\Ciba\BackchannelApproval;

final readonly class ApprovalsSection implements AccountSectionInterface
{
    public function __construct(
        private BackchannelApproval $approval,
    ) {
    }

    public function route(): string
    {
        return PendingApprovalsController::ROUTE;
    }

    public function title(): string
    {
        return 'approvals.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.approvals.state', [
            'count' => count($this->approval->awaitingAnAnswerBy($user)),
        ]);
    }
}
