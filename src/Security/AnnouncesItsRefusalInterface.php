<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

interface AnnouncesItsRefusalInterface extends ProvingLoginInterface
{
    /**
     * @return non-empty-string
     */
    public function route(): string;

    /**
     * @return LoginFailure|null null when nothing was attempted on an account
     */
    public function refusal(AuthenticationException $exception, ?Passport $passport): ?LoginFailure;
}
