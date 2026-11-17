<?php

declare(strict_types=1);

namespace App\Locale;

use Beffroi\Core\Domain\Identity\User;
use DateTimeZone;
use function date_default_timezone_get;
use function in_array;

final readonly class UserTimeZone
{
    public function of(?User $user): DateTimeZone
    {
        $claimed = $user?->profile->zoneinfo;
        if ($claimed !== null && in_array($claimed, DateTimeZone::listIdentifiers(), true)) {
            return new DateTimeZone($claimed);
        }

        return new DateTimeZone(date_default_timezone_get());
    }
}
