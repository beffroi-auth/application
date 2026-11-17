<?php

declare(strict_types=1);

namespace App\Security;

use SensitiveParameter;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * Resolved from the start: the core verified the password before the passport was built. Only the lookup of
 * compromised passwords reads the password, and it must reach no session, no queue and no event.
 */
final readonly class VerifiedPassword implements CredentialsInterface
{
    public function __construct(
        #[SensitiveParameter]
        public string $password,
    ) {
    }

    public function isResolved(): bool
    {
        return true;
    }
}
