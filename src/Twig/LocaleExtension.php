<?php

declare(strict_types=1);

namespace App\Twig;

use App\Http\InteractionLocale;
use App\Locale\ServedLocales;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class LocaleExtension
{
    public function __construct(
        private ServedLocales $served,
        private RequestStack $requests,
    ) {
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction(name: 'beffroi_locales')]
    public function locales(): array
    {
        return $this->served->all;
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction(name: 'beffroi_locale_choice')]
    public function choice(): array
    {
        $request = $this->requests->getMainRequest();

        return $request?->attributes->getBoolean(InteractionLocale::OF_THE_ACCOUNT) === true ? [] : $this->served->all;
    }
}
