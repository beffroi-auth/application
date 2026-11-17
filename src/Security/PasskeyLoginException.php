<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Throwable;

final class PasskeyLoginException extends AuthenticationException
{
    public function __construct(
        public readonly AuthenticationFailureReason $reason,
        public readonly ?AuthenticationMethod $method = null,
        public readonly ?User $user = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Passkey login failed.', 0, $previous);
    }

    public function getMessageKey(): string
    {
        return 'Invalid credentials.';
    }
}
