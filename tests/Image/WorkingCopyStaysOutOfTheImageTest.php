<?php

declare(strict_types=1);

namespace App\Tests\Image;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function substr;

final class WorkingCopyStaysOutOfTheImageTest extends TestCase
{
    #[Test]
    #[DataProvider('provideWhatTheRepositoryIgnores')]
    public function theBuildLeavesOutWhatTheRepositoryIgnores(string $pattern): void
    {
        // Given
        $excluded = BuildContext::ofThisApplication()->exclusions;

        // When / Then
        static::assertContains($pattern, $excluded);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhatTheRepositoryIgnores(): iterable
    {
        foreach (ApplicationFiles::linesOf('.gitignore') as $ignored) {
            if (! str_starts_with($ignored, '!')) {
                yield $ignored => [self::fromTheRootOfTheMonorepo($ignored)];
            }
        }
    }

    private static function fromTheRootOfTheMonorepo(string $ignored): string
    {
        $path = rtrim($ignored, '/');
        if (str_starts_with($path, '/')) {
            return ApplicationFiles::FROM_THE_ROOT . substr($path, 1);
        }

        return ApplicationFiles::FROM_THE_ROOT . (str_contains($path, '/') ? $path : '**/' . $path);
    }
}
