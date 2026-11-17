<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Carries the SHA-1 of the password, never the password, and is sealed on its way out by the serializer of every
 * transport (decision 0020).
 */
final readonly class CheckThePasswordAgainstTheBreaches implements Command
{
    public function __construct(
        public Uuid $credential,
        public string $digest,
    ) {
    }
}
