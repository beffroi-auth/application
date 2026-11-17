<?php

declare(strict_types=1);

namespace App\Http\Account;

use function count;
use App\Controller\AccountPasskeysController;
use Beffroi\Core\Application\Credential\ActiveCredentials;
use Beffroi\Core\Domain\Credential\PasskeyCredential;
use Beffroi\Core\Domain\Identity\User;

final readonly class PasskeysSection implements AccountSectionInterface
{
    public function __construct(
        private ActiveCredentials $active,
    ) {
    }

    public function route(): string
    {
        return AccountPasskeysController::ROUTE;
    }

    public function title(): string
    {
        return 'passkeys.title';
    }

    public function state(User $user): AccountSectionState
    {
        return new AccountSectionState('account.passkeys.state', [
            'count' => count($this->active->of($user, PasskeyCredential::class)),
        ]);
    }
}
