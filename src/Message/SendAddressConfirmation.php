<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Tenant\Tenant;
use DateTimeImmutable;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * Carries the code and the value of the link in a body the transport seals (decision 0020), to the one address they
 * establish.
 */
final readonly class SendAddressConfirmation implements Notification
{
    public function __construct(
        public Tenant $tenant,
        public Uuid $userId,
        public string $claim,
        public string $address,
        public Uuid $confirmation,
        #[SensitiveParameter]
        public string $code,
        #[SensitiveParameter]
        public string $value,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
