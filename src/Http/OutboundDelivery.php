<?php

declare(strict_types=1);

namespace App\Http;

use function sprintf;

final readonly class OutboundDelivery
{
    private function __construct(
        public bool $delivered,
        public bool $final,
        public string $detail,
    ) {
    }

    public static function delivered(int $status): self
    {
        return new self(true, true, sprintf('the client answered %d', $status));
    }

    public static function refused(string $detail): self
    {
        return new self(false, true, $detail);
    }

    public static function unreachable(string $detail): self
    {
        return new self(false, false, $detail);
    }
}
