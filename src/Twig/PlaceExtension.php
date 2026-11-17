<?php

declare(strict_types=1);

namespace App\Twig;

use function array_filter;
use function array_values;
use function implode;
use Beffroi\Core\Domain\Session\GeoLocation;
use Locale;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class PlaceExtension
{
    public function __construct(
        private LocaleSwitcher $switcher,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_place')]
    public function place(?GeoLocation $location): ?string
    {
        if ($location === null) {
            return null;
        }
        $named = array_filter(
            [$location->city, $location->region, $this->country($location->countryCode)],
            static fn (?string $part): bool => $part !== null && $part !== '',
        );
        $written = implode(', ', array_values($named));

        return $written === '' ? null : $written;
    }

    private function country(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $named = Locale::getDisplayRegion('-' . $code, $this->switcher->getLocale());

        return $named === false || $named === '' ? $code : $named;
    }
}
