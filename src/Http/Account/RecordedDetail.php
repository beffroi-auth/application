<?php

declare(strict_types=1);

namespace App\Http\Account;

use DateTimeImmutable;

final readonly class RecordedDetail
{
    private function __construct(
        public string $key,
        public ?string $value,
        public ?DateTimeImmutable $moment,
        public ?bool $verified,
    ) {
    }

    public static function said(string $key, string $value): self
    {
        return new self($key, $value, null, null);
    }

    public static function nothing(string $key): self
    {
        return new self($key, null, null, null);
    }

    public static function verifiable(string $key, string $value, bool $verified): self
    {
        return new self($key, $value, null, $verified);
    }

    public static function dated(string $key, DateTimeImmutable $moment): self
    {
        return new self($key, null, $moment, null);
    }
}
