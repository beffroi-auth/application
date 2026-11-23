<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Locale\ServedLocales;
use function array_diff;
use function array_values;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * Architecture §5.7
 */
final readonly class LocalesSection extends ConfigurationSection
{
    public function name(): string
    {
        return 'locales';
    }

    public function describe(NodeBuilder $children): void
    {
        $children->append(self::locales());
    }

    /**
     * @param list<string> $enabledLocales the locales the application has a catalogue for
     *
     * @throws InvalidConfigurationException
     */
    public static function served(array $enabledLocales, string $defaultLocale, mixed $chosen): ServedLocales
    {
        $locales = [];
        foreach (self::locales()->normalized($chosen, 'locales') as $locale) {
            if (! is_string($locale)) {
                throw new InvalidConfigurationException('The "beffroi" configuration key "locales" must be a list of strings.');
            }
            $locales[] = $locale;
        }
        if (! in_array($defaultLocale, $enabledLocales, true)) {
            throw new InvalidConfigurationException(sprintf(
                'The default locale "%s" has no catalogue in the application, which has "%s".',
                $defaultLocale,
                implode('", "', $enabledLocales),
            ));
        }
        if ($locales === []) {
            return new ServedLocales($enabledLocales, $defaultLocale);
        }
        $unknown = array_values(array_diff($locales, $enabledLocales));
        if ($unknown !== []) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "locales" names "%s", which no catalogue of the application holds; it has "%s".',
                implode('", "', $unknown),
                implode('", "', $enabledLocales),
            ));
        }
        if (! in_array($defaultLocale, $locales, true)) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "locales" leaves out the default locale "%s": name it, or set the default locale to one of "%s".',
                $defaultLocale,
                implode('", "', $locales),
            ));
        }

        return new ServedLocales($locales, $defaultLocale);
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $services
            ->get(ServedLocales::class)
            ->factory(self::served(...))
            ->args(['%kernel.enabled_locales%', '%kernel.default_locale%', $config['locales'] ?? []]);
    }

    private static function locales(): EnvironmentListDefinition
    {
        $locales = new EnvironmentListDefinition('locales');
        $locales
            ->info('The languages the pages and the messages are served in, among those the application has a catalogue for ("framework.enabled_locales"), the default locale ("framework.default_locale") among them. Empty, the default, serves every one. The discovery document announces them in "ui_locales_supported" (OpenID Connect Discovery 1.0 §3).')
            ->scalarPrototype()
            ->cannotBeEmpty()
            ->end()
            ->defaultValue([]);

        return $locales;
    }
}
