<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Throwable;

/**
 * Reads the chain of causes: Symfony reports a refusal under the exception of the authenticator.
 */
final readonly class AuthenticationFailureCause
{
    public static function of(AuthenticationException $exception): ?AuthenticationFailureReason
    {
        for ($cause = $exception; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if ($cause instanceof UserNotFoundException) {
                return AuthenticationFailureReason::UnknownUser;
            }
            if ($cause instanceof DisabledException) {
                return AuthenticationFailureReason::DisabledUser;
            }
            if ($cause instanceof TooManyLoginAttemptsAuthenticationException) {
                return AuthenticationFailureReason::TooManyAttempts;
            }
        }

        return null;
    }
}
