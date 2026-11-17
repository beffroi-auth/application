<?php

declare(strict_types=1);

namespace App\Tests\Image;

use function array_filter;
use function array_key_last;
use function array_map;
use function array_slice;
use function explode;
use function fnmatch;
use const FNM_PATHNAME;
use function rtrim;
use function str_starts_with;
use function strpbrk;
use function substr;

final readonly class BuildContext
{
    /**
     * @param list<string> $exclusions
     */
    private function __construct(
        public array $exclusions,
    ) {
    }

    public static function ofThisApplication(): self
    {
        $patterns = ApplicationFiles::linesOf('Dockerfile.dockerignore');
        $inclusions = array_filter($patterns, static fn (string $pattern): bool => str_starts_with($pattern, '!'));
        $lastInclusion = array_key_last($inclusions) ?? -1;

        return new self(array_map(
            static fn (string $pattern): string => rtrim($pattern, '/'),
            array_slice($patterns, $lastInclusion + 1),
        ));
    }

    public function leavesOut(string $file): bool
    {
        $path = ApplicationFiles::FROM_THE_ROOT . $file;
        foreach ($this->exclusions as $pattern) {
            if (str_starts_with($pattern, '**/')) {
                foreach (explode('/', $path) as $name) {
                    if (fnmatch(substr($pattern, 3), $name)) {
                        return true;
                    }
                }

                continue;
            }
            if ($path === $pattern || str_starts_with($path, $pattern . '/')) {
                return true;
            }
            if (strpbrk($pattern, '*?[') !== false && fnmatch($pattern, $path, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }
}
