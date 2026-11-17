<?php

declare(strict_types=1);

namespace App\Http;

interface BrowserPreferenceInterface
{
    public function slot(): string;

    /**
     * Canonical, or null for a value this deployment does not serve.
     */
    public function read(string $value): ?string;
}
