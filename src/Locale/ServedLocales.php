<?php

declare(strict_types=1);

namespace App\Locale;

use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function ctype_alpha;
use function ctype_digit;
use function implode;
use function in_array;
use function preg_split;
use function strlen;
use function strtolower;
use function strtoupper;
use function ucfirst;

final readonly class ServedLocales
{
    /**
     * A language, three extended language subtags, a script and a region (RFC 5646 §2.1): what follows names neither.
     */
    private const int UP_TO_THE_REGION = 6;

    /**
     * @var list<string> canonical, the default locale first
     */
    public array $all;

    public string $default;

    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(array $enabledLocales, string $defaultLocale)
    {
        $this->default = self::canonical($defaultLocale);
        $canonical = array_map(self::canonical(...), [$defaultLocale, ...$enabledLocales]);
        $this->all = array_values(array_unique(array_filter($canonical, static fn (string $locale): bool => $locale !== '')));
    }

    /**
     * @param list<string> $tags most preferred first
     */
    public function match(array $tags): ?string
    {
        foreach ($tags as $tag) {
            foreach (self::candidates($tag) as $candidate) {
                if (in_array($candidate, $this->all, true)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * "fr_CA", "zh_Hant_TW"; "" for a tag that names no language.
     */
    private static function canonical(string $tag): string
    {
        return self::candidates($tag)[0] ?? '';
    }

    /**
     * RFC 5646 §2.1, §2.2
     *
     * @return list<string> most specific first
     */
    private static function candidates(string $tag): array
    {
        $subtags = preg_split('/[-_]/', $tag, self::UP_TO_THE_REGION + 1);
        if ($subtags === false) {
            return [];
        }
        $language = strtolower($subtags[0] ?? '');
        if ($language === '' || ! ctype_alpha($language)) {
            return [];
        }
        $script = null;
        $region = null;
        foreach (array_slice($subtags, 1, self::UP_TO_THE_REGION - 1) as $subtag) {
            $length = strlen($subtag);
            if ($script === null && $region === null && $length === 4 && ctype_alpha($subtag)) {
                $script = ucfirst(strtolower($subtag));
            } elseif ($region === null && ($length === 2 && ctype_alpha($subtag) || $length === 3 && ctype_digit($subtag))) {
                $region = strtoupper($subtag);
            }
        }

        $present = static fn (?string $subtag): bool => $subtag !== null;

        return array_values(array_unique([
            implode('_', array_filter([$language, $script, $region], $present)),
            implode('_', array_filter([$language, $script], $present)),
            implode('_', array_filter([$language, $region], $present)),
            $language,
        ]));
    }
}
