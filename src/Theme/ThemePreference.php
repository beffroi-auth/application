<?php

declare(strict_types=1);

namespace App\Theme;

use App\Http\BrowserPreferenceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('beffroi.browser_preference')]
final readonly class ThemePreference implements BrowserPreferenceInterface
{
    public const string SLOT = 'theme';

    public function slot(): string
    {
        return self::SLOT;
    }

    public function read(string $value): ?string
    {
        return Theme::tryFrom($value)?->value;
    }
}
