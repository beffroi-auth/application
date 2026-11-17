<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A code and a field, never the value a person typed: it crosses a redirection in the session, which is a disk.
 */
final readonly class Refusal
{
    public function __construct(
        public string $code,
        public ?string $field = null,
    ) {
    }
}
