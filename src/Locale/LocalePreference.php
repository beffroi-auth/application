<?php

declare(strict_types=1);

namespace App\Locale;

use App\Http\BrowserPreferenceInterface;

final readonly class LocalePreference implements BrowserPreferenceInterface
{
    public const string SLOT = 'locale';

    public function __construct(
        private ServedLocales $served,
    ) {
    }

    public function slot(): string
    {
        return self::SLOT;
    }

    public function read(string $value): ?string
    {
        return $this->served->match([$value]);
    }
}
