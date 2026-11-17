<?php

declare(strict_types=1);

namespace App\Command;

use function array_keys;
use function array_map;
use function implode;
use function is_string;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function ksort;

/**
 * Members of a client as a command prints them: a string as it is, anything else as the JSON it is stored as.
 */
final readonly class PrintedMembers
{
    /**
     * @param array<string, mixed> $members
     *
     * @return list<array{string, string}> by member name
     */
    public static function rows(array $members): array
    {
        ksort($members);

        return array_map(
            static fn (string $name): array => [$name, self::shown($members[$name])],
            array_keys($members),
        );
    }

    /**
     * @param array<string, mixed> $members
     */
    public static function lines(array $members): string
    {
        return implode("\n", array_map(static fn (array $row): string => $row[0] . ': ' . $row[1], self::rows($members)));
    }

    private static function shown(mixed $value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
