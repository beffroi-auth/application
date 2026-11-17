<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use function bin2hex;
use DateTimeImmutable;

final readonly class StoredDataKeyDescription
{
    public function __construct(
        public string $reference,
        public ?DateTimeImmutable $mintedAt,
        public string $masterKeyId,
        public string $client,
    ) {
    }

    public function hex(): string
    {
        return bin2hex($this->reference);
    }
}
