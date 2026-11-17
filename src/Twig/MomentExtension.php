<?php

declare(strict_types=1);

namespace App\Twig;

use App\Locale\UserTimeZone;
use Beffroi\Core\Domain\Identity\User;
use DateTimeImmutable;
use IntlDateFormatter;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Attribute\AsTwigFilter;

final readonly class MomentExtension
{
    public const string WITHOUT_ICU = 'Y-m-d H:i T';

    private const string CLOCK = ' zzz';

    public function __construct(
        private UserTimeZone $zones,
        private LocaleSwitcher $switcher,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_moment')]
    public function moment(DateTimeImmutable $moment, ?User $reader = null): string
    {
        $zone = $this->zones->of($reader);
        $formatter = new IntlDateFormatter(
            $this->switcher->getLocale(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT,
            $zone,
        );
        $pattern = $formatter->getPattern();
        if ($pattern !== false) {
            $formatter->setPattern($pattern . self::CLOCK);
        }
        $written = $formatter->format($moment);

        return $written === false ? $moment->setTimezone($zone)->format(self::WITHOUT_ICU) : $written;
    }
}
