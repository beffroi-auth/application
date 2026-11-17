<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Application\Totp\TotpCodeMatcher;
use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use SensitiveParameter;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Says what was typed and never why the code was refused, which would tell whether the credential exists.
 *
 * The value is the "data-error" of the page and the last part of the message key.
 */
enum CodeRefusal: string
{
    case Code = 'code_refused';

    case BackupCode = 'backup_code_refused';

    case TooManyAttempts = 'too_many_attempts';

    public static function of(#[SensitiveParameter] string $typed, AuthenticationFailureReason $reason): self
    {
        if ($reason === AuthenticationFailureReason::TooManyAttempts) {
            return self::TooManyAttempts;
        }

        return TotpCodeMatcher::looksLikeCode($typed) ? self::Code : self::BackupCode;
    }

    public static function shown(?AuthenticationException $error): ?self
    {
        if ($error === null) {
            return null;
        }

        return $error instanceof CodeRefusedException ? $error->refusal : self::Code;
    }
}
