<?php

declare(strict_types=1);

namespace App\Theme;

use App\Http\BrowserPreferences;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ChosenTheme
{
    public function __construct(
        private RequestStack $requests,
        private BrowserPreferences $preferences,
    ) {
    }

    public function current(): Theme
    {
        $request = $this->requests->getCurrentRequest();

        return Theme::ofChoice($request === null ? null : $this->preferences->read($request, ThemePreference::SLOT));
    }

    public function cookie(Request $request, Theme $theme): Cookie
    {
        return $this->preferences->cookie($request, ThemePreference::SLOT, $theme->value);
    }
}
