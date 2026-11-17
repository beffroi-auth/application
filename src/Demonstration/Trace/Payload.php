<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function parse_str;
use function parse_url;
use const PHP_URL_QUERY;
use function sprintf;
use function str_contains;
use function str_starts_with;

final readonly class Payload
{
    /**
     * @param array<string, CompactJwt> $jwts by the name the payload gave them
     */
    private function __construct(
        public string $raw,
        public ?string $indented,
        public array $jwts,
    ) {
    }

    public static function of(string $raw): self
    {
        $object = json_decode($raw, true);
        if (is_array($object) && $object !== []) {
            $indented = json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return new self($raw, $indented === false ? null : $indented, self::jwtsIn($object));
        }

        return new self($raw, null, self::jwtsIn(self::query($raw) ?? ['payload' => $raw]));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function query(string $raw): ?array
    {
        $query = str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')
            ? parse_url($raw, PHP_URL_QUERY)
            : $raw;
        if (! is_string($query) || ! str_contains($query, '=')) {
            return null;
        }
        parse_str($query, $fields);

        return $fields === [] ? null : $fields;
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @return array<string, CompactJwt>
     */
    private static function jwtsIn(array $fields, string $under = ''): array
    {
        $jwts = [];
        foreach ($fields as $name => $value) {
            $path = $under === '' ? (string) $name : sprintf('%s.%s', $under, (string) $name);
            if (is_array($value)) {
                $jwts = [...$jwts, ...self::jwtsIn($value, $path)];

                continue;
            }
            if (! is_string($value)) {
                continue;
            }
            $jwt = CompactJwt::read($value);
            if ($jwt !== null) {
                $jwts[$path] = $jwt;
            }
        }

        return $jwts;
    }
}
