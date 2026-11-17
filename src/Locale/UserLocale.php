<?php

declare(strict_types=1);

namespace App\Locale;

use Beffroi\Core\Domain\Identity\User;

/**
 * The language a user is written to outside a request of theirs: their "locale" when served, else the default.
 */
final readonly class UserLocale
{
    public function __construct(
        private ServedLocales $served,
    ) {
    }

    public function of(User $user): string
    {
        return $this->claimedBy($user) ?? $this->served->default;
    }

    public function claimedBy(User $user): ?string
    {
        $claimed = $user->profile->locale;

        return $claimed === null ? null : $this->served->match([$claimed]);
    }
}
