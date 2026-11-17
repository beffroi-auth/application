<?php

declare(strict_types=1);

namespace App\Push;

use function sprintf;

final readonly class PushDelivery
{
    private function __construct(
        public bool $delivered,
        public bool $gone,
        public string $detail,
    ) {
    }

    public static function delivered(int $status): self
    {
        return new self(true, false, sprintf('the push service answered %d', $status));
    }

    /**
     * RFC 8030 §7.3
     */
    public static function gone(string $detail): self
    {
        return new self(false, true, $detail);
    }

    public static function unreachable(string $detail): self
    {
        return new self(false, false, $detail);
    }
}
