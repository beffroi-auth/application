<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\PendingLogin;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * Resolved from the start: the core verified the code before the passport was built.
 */
final readonly class TotpCredentials implements CredentialsInterface
{
    public function __construct(
        public PendingLogin $login,
        public AuthenticationMethod $method,
    ) {
    }

    public function isResolved(): bool
    {
        return true;
    }
}
