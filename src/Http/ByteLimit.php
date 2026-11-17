<?php

declare(strict_types=1);

namespace App\Http;

use function strlen;

final readonly class ByteLimit
{
    public function __construct(
        public int $bytes,
    ) {
    }

    public function isExceededBy(string $body): bool
    {
        return strlen($body) > $this->bytes;
    }
}
