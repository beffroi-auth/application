<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Session\AuthenticationMethod;
use DateTimeImmutable;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * Resolved from the start: the assertion consumer service verified the response before the passport was built.
 */
final readonly class UpstreamCredentials implements CredentialsInterface
{
    /**
     * @param non-empty-list<AuthenticationMethod> $methods
     */
    public function __construct(
        public array $methods,
        public DateTimeImmutable $provenAt,
        public ?DateTimeImmutable $sessionNotOnOrAfter = null,
    ) {
    }

    public function isResolved(): bool
    {
        return true;
    }
}
