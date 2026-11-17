<?php

declare(strict_types=1);

namespace App\Identity;

use App\Locale\KnownLocales;
use Beffroi\Core\Domain\Identity\Claim\Locale;

final readonly class LocaleClaimFactory
{
    public static function of(KnownLocales $known): Locale
    {
        return new Locale($known->all);
    }
}
