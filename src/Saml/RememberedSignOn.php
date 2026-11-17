<?php

declare(strict_types=1);

namespace App\Saml;

use App\Http\WaitingPage;
use DateTimeImmutable;

final readonly class RememberedSignOn
{
    private function __construct(
        public string $requestId,
        public string $identityProvider,
        public bool $forceAuthn,
        public DateTimeImmutable $issuedAt,
        public ?string $interaction,
        public ?WaitingPage $page,
    ) {
    }

    public static function forInteraction(string $interaction, string $requestId, string $identityProvider, bool $forceAuthn, DateTimeImmutable $issuedAt): self
    {
        return new self($requestId, $identityProvider, $forceAuthn, $issuedAt, $interaction, null);
    }

    public static function forPage(WaitingPage $page, string $requestId, string $identityProvider, bool $forceAuthn, DateTimeImmutable $issuedAt): self
    {
        return new self($requestId, $identityProvider, $forceAuthn, $issuedAt, null, $page);
    }
}
