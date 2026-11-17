<?php

declare(strict_types=1);

namespace App\Http\Account;

use Beffroi\Core\Domain\Identity\User;

interface AccountSectionInterface
{
    public function route(): string;

    /**
     * A translation key.
     */
    public function title(): string;

    public function state(User $user): AccountSectionState;
}
