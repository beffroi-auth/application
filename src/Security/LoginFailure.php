<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationMethod;

final readonly class LoginFailure
{
    public function __construct(
        public ?AuthenticationMethod $method,
        public AuthenticationFailureReason $reason,
        public ?string $identifier,
        public ?User $user,
    ) {
    }
}
