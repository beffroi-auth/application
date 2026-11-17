<?php

declare(strict_types=1);

namespace App\Tests\Image;

use function array_diff;
use function array_pop;
use function array_unique;
use function basename;
use function explode;
use function file_get_contents;
use function fnmatch;
use function glob;
use function in_array;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function preg_match;
use function preg_split;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Decision 0030
 */
final class TheImageCarriesWhatADeploymentServesTest extends TestCase
{
    private const string DEVELOPMENT_STAGE = 'frankenphp_dev';

    #[Test]
    #[DataProvider('provideWhatOnlyAnotherEnvironmentReads')]
    public function theBuildLeavesOutWhatOnlyAnotherEnvironmentReads(string $file): void
    {
        // Given
        $context = BuildContext::ofThisApplication();

        // When / Then
        static::assertTrue($context->leavesOut($file));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhatOnlyAnotherEnvironmentReads(): iterable
    {
        foreach (ConfigurationFile::everyOne() as $configuration) {
            if ($configuration->isOfAnotherEnvironment()) {
                yield $configuration->file => [$configuration->file];
            }
        }
        $environments = glob(ApplicationFiles::path('.env.*'));
        foreach ($environments === false ? [] : $environments as $path) {
            $file = basename($path);
            if (preg_match('/^\.env\.([a-z]+)$/D', $file, $environment) === 1 && ! in_array($environment[1], ['local', 'prod'], true)) {
                yield $file => [$file];
            }
        }
    }

    #[Test]
    #[DataProvider('provideTheConfigurationOfProduction')]
    public function aFileThatConfiguresProductionConfiguresNoOtherEnvironment(string $file): void
    {
        // Given
        $configuration = ConfigurationFile::of($file);

        // When / Then
        static::assertSame([], $configuration->otherEnvironments());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheConfigurationOfProduction(): iterable
    {
        foreach (ConfigurationFile::everyOne() as $configuration) {
            if (! $configuration->isOfAnotherEnvironment()) {
                yield $configuration->file => [$configuration->file];
            }
        }
    }

    #[Test]
    #[DataProvider('provideTheVariablesOnlyTheConfigurationOfAnotherEnvironmentReads')]
    public function theImageCompilesNoVariableOnlyTheConfigurationOfAnotherEnvironmentReads(string $variable): void
    {
        // Given
        $compiled = self::variablesTheImageCompiles();

        // When / Then
        static::assertNotContains($variable, $compiled);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheVariablesOnlyTheConfigurationOfAnotherEnvironmentReads(): iterable
    {
        $production = [];
        $elsewhere = [];
        foreach (ConfigurationFile::everyOne() as $configuration) {
            $production = [...$production, ...$configuration->variablesReadInProduction()];
            $elsewhere = [...$elsewhere, ...$configuration->variablesReadElsewhere()];
        }
        foreach (array_unique(array_diff($elsewhere, $production)) as $variable) {
            yield $variable => [$variable];
        }
    }

    #[Test]
    #[DataProvider('provideWhatTheConfigurationOfAnotherEnvironmentNames')]
    public function theBuildLeavesOutWhatTheConfigurationOfAnotherEnvironmentNames(string $file): void
    {
        // Given
        $context = BuildContext::ofThisApplication();

        // When / Then
        static::assertTrue($context->leavesOut($file));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhatTheConfigurationOfAnotherEnvironmentNames(): iterable
    {
        foreach (ConfigurationFile::everyOne() as $configuration) {
            if ($configuration->isOfAnotherEnvironment()) {
                foreach ($configuration->namedPaths() as $file) {
                    yield sprintf('%s, named by %s', $file, $configuration->file) => [$file];
                }
            }
        }
    }

    #[Test]
    #[DataProvider('provideWhatTheDevelopmentImageCopies')]
    public function theProductionImageLeavesOutWhatTheDevelopmentImageCopies(string $file): void
    {
        // Given
        $excluded = self::excludedFromTheCopyOfTheApplication();

        // When / Then
        static::assertTrue(self::isAmong($file, $excluded));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhatTheDevelopmentImageCopies(): iterable
    {
        foreach (self::copies() as $copy) {
            if ($copy['stage'] !== self::DEVELOPMENT_STAGE || $copy['from'] !== null) {
                continue;
            }
            foreach ($copy['sources'] as $source) {
                if (str_starts_with($source, ApplicationFiles::FROM_THE_ROOT)) {
                    $file = substr($source, strlen(ApplicationFiles::FROM_THE_ROOT));
                    yield $file => [$file];
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function excludedFromTheCopyOfTheApplication(): array
    {
        foreach (self::copies() as $copy) {
            if ($copy['from'] === null && $copy['sources'] === [ApplicationFiles::FROM_THE_ROOT]) {
                return $copy['excluded'];
            }
        }

        static::fail(sprintf('No COPY of apps/beffroi/Dockerfile copies "%s" whole.', ApplicationFiles::FROM_THE_ROOT));
    }

    /**
     * @param list<string> $excluded
     */
    private static function isAmong(string $file, array $excluded): bool
    {
        foreach ($excluded as $pattern) {
            if ($file === $pattern || str_starts_with($file, $pattern . '/') || fnmatch($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function variablesTheImageCompiles(): array
    {
        $variables = [];
        foreach (ApplicationFiles::linesOf('.env') as $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=/D', $line, $declaration) === 1) {
                $variables[] = $declaration[1];
            }
        }

        return $variables;
    }

    /**
     * @return list<array{stage: string, from: ?string, excluded: list<string>, sources: list<string>}>
     */
    private static function copies(): array
    {
        $content = file_get_contents(ApplicationFiles::path('Dockerfile'));
        if ($content === false) {
            static::fail('The Dockerfile of the application cannot be read.');
        }
        $copies = [];
        $stage = '';
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (preg_match('/^FROM\s+\S+\s+AS\s+(\S+)$/Di', $line, $from) === 1) {
                $stage = $from[1];

                continue;
            }
            if (! str_starts_with($line, 'COPY ')) {
                continue;
            }
            $copy = ['stage' => $stage, 'from' => null, 'excluded' => [], 'sources' => []];
            $words = preg_split('/\s+/', substr($line, strlen('COPY ')));
            foreach ($words === false ? [] : $words as $word) {
                if (preg_match('/^--from=(.+)$/D', $word, $flag) === 1) {
                    $copy['from'] = $flag[1];
                } elseif (preg_match('/^--exclude=(.+)$/D', $word, $flag) === 1) {
                    $copy['excluded'][] = $flag[1];
                } elseif (! str_starts_with($word, '--')) {
                    $copy['sources'][] = $word;
                }
            }
            // The last word is where the copy lands.
            array_pop($copy['sources']);
            $copies[] = $copy;
        }

        return $copies;
    }
}
