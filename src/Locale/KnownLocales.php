<?php

declare(strict_types=1);

namespace App\Locale;

use function array_key_exists;
use function asort;
use function is_string;
use Locale as IcuLocale;
use ResourceBundle;
use const SORT_STRING;
use function str_replace;

/**
 * The languages a person may say they read, which ICU has data for; not those this deployment serves (ServedLocales).
 */
final readonly class KnownLocales
{
    /**
     * @var array<string, string> names in themselves by canonical tag, sorted by name
     */
    public array $all;

    public function __construct()
    {
        $names = [];
        $locales = ResourceBundle::getLocales('');
        foreach ($locales === false ? [] : $locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }
            $tag = str_replace('_', '-', IcuLocale::canonicalize($locale) ?? $locale);
            if ($tag === '' || array_key_exists($tag, $names)) {
                continue;
            }
            $name = IcuLocale::getDisplayName($locale, $locale);
            $names[$tag] = is_string($name) && $name !== '' ? $name : $tag;
        }
        asort($names, SORT_STRING);
        $this->all = $names;
    }
}
