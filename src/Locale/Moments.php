<?php

declare(strict_types=1);

namespace App\Locale;

use Beffroi\Core\Domain\Identity\User;
use DateTimeImmutable;
use IntlDateFormatter;

/**
 * A moment as the person it is written for reads it: on their clock, which it names, in the language given.
 */
final readonly class Moments
{
    public const string WITHOUT_ICU = 'Y-m-d H:i T';

    private const string CLOCK = ' zzz';

    /**
     * The English of the catalogue is British and its Portuguese that of Portugal, which ICU takes neither "en" nor
     * "pt" for.
     */
    private const array WRITTEN_AS = ['en' => 'en_GB', 'pt' => 'pt_PT'];

    public function __construct(
        private UserTimeZone $zones,
    ) {
    }

    public function write(DateTimeImmutable $moment, ?User $reader, string $locale): string
    {
        $zone = $this->zones->of($reader);
        $formatter = new IntlDateFormatter(self::WRITTEN_AS[$locale] ?? $locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, $zone);
        $pattern = $formatter->getPattern();
        if ($pattern !== false) {
            $formatter->setPattern($pattern . self::CLOCK);
        }
        $written = $formatter->format($moment);

        return $written === false ? $moment->setTimezone($zone)->format(self::WITHOUT_ICU) : $written;
    }
}
