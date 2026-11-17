<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Credential\TotpCredential;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final readonly class TotpLogin implements ProvingLoginInterface
{
    public function proofs(Passport $passport): ?array
    {
        $badge = $passport->getBadge(TotpCredentials::class);
        if (! $badge instanceof TotpCredentials) {
            return null;
        }

        return [...$badge->login->methods, $badge->method];
    }

    public function everyProvableMethod(): array
    {
        return [TotpCredential::method()];
    }
}
