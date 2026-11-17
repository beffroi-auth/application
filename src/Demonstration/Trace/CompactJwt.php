<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use function base64_decode;
use function count;
use function explode;
use function is_array;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function strtr;

/**
 * Read for display only: nothing is verified and nothing is decrypted.
 */
final readonly class CompactJwt
{
    private function __construct(
        public string $raw,
        public string $header,
        public ?string $claims,
    ) {
    }

    public static function read(string $value): ?self
    {
        $parts = explode('.', $value);
        if (count($parts) !== 3 && count($parts) !== 5) {
            return null;
        }
        $header = self::object($parts[0]);
        if ($header === null) {
            return null;
        }
        $claims = count($parts) === 3 ? self::object($parts[1]) : null;

        return new self($value, $header, $claims);
    }

    public function encrypted(): bool
    {
        return $this->claims === null;
    }

    private static function object(string $part): ?string
    {
        $decoded = base64_decode(strtr($part, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }
        $object = json_decode($decoded, true);
        if (! is_array($object) || $object === []) {
            return null;
        }
        $written = json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $written === false ? null : $written;
    }
}
