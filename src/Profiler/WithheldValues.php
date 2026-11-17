<?php

declare(strict_types=1);

namespace App\Profiler;

use function in_array;
use function is_scalar;
use function str_ends_with;

/**
 * Withholds credentials, and what stands for a request in progress, which its specification asks to resist
 * guessing (RFC 8628 §5.1, §5.2, CIBA Core §7.3, RFC 9126 §7.1).
 */
final readonly class WithheldValues
{
    private const array NAMES = [
        'token',
        'code',
        'code_verifier',
        'client_secret',
        'request',
        'client_assertion',
        'dpop',
        'device_code',
        'user_code',
        'verification_uri_complete',
        'auth_req_id',
        'request_uri',
    ];

    public static function withholds(string $name): bool
    {
        return in_array($name, self::NAMES, true) || str_ends_with($name, '_token') || str_ends_with($name, '_token_hint');
    }

    public static function of(string $name, mixed $value): string
    {
        if (self::withholds($name)) {
            return '(present, withheld)';
        }

        return is_scalar($value) ? (string) $value : '(a structure, withheld)';
    }
}
