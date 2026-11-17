<?php

declare(strict_types=1);

namespace App\Tests\Image;

use const ARRAY_FILTER_USE_KEY;
use function array_filter;
use function array_keys;
use function array_map;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_match_all;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function sort;
use function str_starts_with;
use function strlen;
use function strpbrk;
use function substr;
use Symfony\Component\Yaml\Yaml;

final readonly class ConfigurationFile
{
    private const string PRODUCTION = 'prod';

    private const string PROJECT_DIRECTORY = '%kernel.project_dir%/';

    /**
     * @param array<mixed> $content
     */
    private function __construct(
        public string $file,
        private array $content,
    ) {
    }

    /**
     * @return list<self>
     */
    public static function everyOne(): array
    {
        $directory = ApplicationFiles::path('config');
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $entry) {
            if ($entry instanceof SplFileInfo && in_array($entry->getExtension(), ['yaml', 'yml'], true)) {
                $files[] = 'config/' . substr($entry->getPathname(), strlen($directory) + 1);
            }
        }
        sort($files);

        return array_map(self::of(...), $files);
    }

    public static function of(string $file): self
    {
        $content = Yaml::parseFile(ApplicationFiles::path($file), Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);

        return new self($file, is_array($content) ? $content : []);
    }

    /**
     * @return list<string>
     */
    public function otherEnvironments(): array
    {
        $environments = [];
        foreach (array_keys($this->content) as $key) {
            if (is_string($key) && $this->isASectionOfAnotherEnvironment($key)) {
                $environments[] = substr($key, strlen('when@'));
            }
        }

        return $environments;
    }

    public function isOfAnotherEnvironment(): bool
    {
        if (preg_match('#^config/(?:packages|routes)/([^/]+)/#', $this->file, $directory) === 1) {
            return $directory[1] !== self::PRODUCTION;
        }
        if (preg_match('#^config/services_([^/]+)\.ya?ml$#D', $this->file, $services) === 1) {
            return $services[1] !== self::PRODUCTION;
        }

        return $this->content !== [] && count($this->otherEnvironments()) === count($this->content);
    }

    /**
     * @return list<string>
     */
    public function variablesReadInProduction(): array
    {
        if ($this->isOfAnotherEnvironment()) {
            return [];
        }

        return self::variablesIn(array_filter($this->content, fn (mixed $key): bool => ! $this->isASectionOfAnotherEnvironment($key), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @return list<string>
     */
    public function variablesReadElsewhere(): array
    {
        if ($this->isOfAnotherEnvironment()) {
            return self::variablesIn($this->content);
        }

        return self::variablesIn(array_filter($this->content, $this->isASectionOfAnotherEnvironment(...), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @return list<string>
     */
    public function namedPaths(): array
    {
        $paths = array_filter(
            array_map(self::inTheApplication(...), [...$this->loadedResources(), ...self::underTheProject($this->content)]),
            static fn (?string $path): bool => $path !== null,
        );

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function loadedResources(): array
    {
        $routes = $this->file === 'config/routes.yaml' || str_starts_with($this->file, 'config/routes/');
        $resources = [];
        foreach (self::sectionsOf($this->content) as $section) {
            $entries = $routes ? $section : [...self::listOf($section['imports'] ?? []), ...self::listOf($section['services'] ?? [])];
            foreach ($entries as $entry) {
                $resource = is_array($entry) ? $entry['resource'] ?? null : $entry;
                if (! is_string($resource) || is_array($entry) && ($entry['type'] ?? null) === 'service' || str_starts_with($resource, '@')) {
                    continue;
                }
                $resources[] = dirname($this->file) . '/' . $resource;
            }
        }

        return $resources;
    }

    /**
     * @param array<mixed> $content
     *
     * @return list<array<mixed>>
     */
    private static function sectionsOf(array $content): array
    {
        $sections = [array_filter($content, static fn (mixed $key): bool => ! is_string($key) || ! str_starts_with($key, 'when@'), ARRAY_FILTER_USE_KEY)];
        foreach ($content as $key => $section) {
            if (is_string($key) && str_starts_with($key, 'when@') && is_array($section)) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @return array<mixed>
     */
    private static function listOf(mixed $entries): array
    {
        return is_array($entries) ? $entries : [];
    }

    /**
     * @param array<mixed> $node
     *
     * @return list<string>
     */
    private static function underTheProject(array $node): array
    {
        $paths = [];
        foreach ($node as $value) {
            if (is_string($value) && str_starts_with($value, self::PROJECT_DIRECTORY)) {
                $paths[] = substr($value, strlen(self::PROJECT_DIRECTORY));
            }
            if (is_array($value)) {
                $paths = [...$paths, ...self::underTheProject($value)];
            }
        }

        return $paths;
    }

    private function isASectionOfAnotherEnvironment(mixed $key): bool
    {
        return is_string($key) && preg_match('/^when@(.+)$/D', $key, $section) === 1 && $section[1] !== self::PRODUCTION;
    }

    /**
     * @param array<mixed> $node
     *
     * @return list<string>
     */
    private static function variablesIn(array $node): array
    {
        $variables = [];
        foreach ($node as $key => $value) {
            if (is_string($key) && preg_match('/^env\((?:[^:()]*:)*([^:()]+)\)$/D', $key, $default) === 1) {
                $variables[] = $default[1];
            }
            if (is_string($value) && preg_match_all('/%env\((?:[^:()%]*:)*([^:()%]+)\)%/', $value, $placeholders) > 0) {
                $variables = [...$variables, ...$placeholders[1]];
            }
            if (is_array($value)) {
                $variables = [...$variables, ...self::variablesIn($value)];
            }
        }

        return array_values(array_unique($variables));
    }

    private static function inTheApplication(string $path): ?string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '%')) {
                return null;
            }
            if (strpbrk($segment, '*?[{') !== false) {
                break;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);

                continue;
            }
            if ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
