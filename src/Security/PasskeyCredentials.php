<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Credential\VerifiedPasskeyAssertion;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * Resolved from the start: the ceremony checked the signature before the passport was built.
 */
final readonly class PasskeyCredentials implements CredentialsInterface
{
    public function __construct(
        public VerifiedPasskeyAssertion $assertion,
    ) {
    }

    public function isResolved(): bool
    {
        return true;
    }
}
