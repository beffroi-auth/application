<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use DateTimeImmutable;

final readonly class TracedHop
{
    private function __construct(
        public string $hop,
        public DateTimeImmutable $at,
        public string $payload,
        public ?string $method,
        public ?string $url,
        public ?int $status,
        public bool $failed,
        public ?string $reason,
    ) {
    }

    public static function sent(string $hop, DateTimeImmutable $at, string $method, string $url, string $payload): self
    {
        return new self($hop, $at, $payload, $method, $url, null, false, null);
    }

    public static function answered(string $hop, DateTimeImmutable $at, int $status, string $payload, ?string $reason = null): self
    {
        return new self($hop, $at, $payload, null, null, $status, $status >= 400 || $reason !== null, $reason);
    }

    public static function unreachable(string $hop, DateTimeImmutable $at, string $reason): self
    {
        return new self($hop, $at, '', null, null, null, true, $reason);
    }
}
