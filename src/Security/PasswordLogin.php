<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final readonly class PasswordLogin implements OfferedLoginInterface
{
    public function kind(): string
    {
        return 'password';
    }

    public function proofs(Passport $passport): ?array
    {
        return $passport->hasBadge(VerifiedPassword::class) ? [AuthenticationMethod::password()] : null;
    }

    public function everyProvableMethod(): array
    {
        return [AuthenticationMethod::password()];
    }
}
