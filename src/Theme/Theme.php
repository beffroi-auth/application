<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * System names no "data-theme": DaisyUI then follows prefers-color-scheme (assets/styles/app.css).
 */
enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public static function ofChoice(?string $choice): self
    {
        return self::tryFrom($choice ?? '') ?? self::System;
    }

    public function attribute(): ?string
    {
        return $this === self::System ? null : $this->value;
    }
}
