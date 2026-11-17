<?php

declare(strict_types=1);

namespace App\Tests\Image;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use PHPUnit\Framework\Assert;
use function sprintf;
use function str_starts_with;
use function trim;

final class ApplicationFiles
{
    public const string FROM_THE_ROOT = 'apps/beffroi/';

    public static function path(string $file): string
    {
        return dirname(__DIR__, 2) . '/' . $file;
    }

    /**
     * @return list<string> neither empty nor a comment
     */
    public static function linesOf(string $file): array
    {
        $content = file_get_contents(self::path($file));
        if ($content === false) {
            Assert::fail(sprintf('The file "%s" of the application cannot be read.', $file));
        }

        return array_values(array_filter(
            array_map(trim(...), explode("\n", $content)),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }
}
