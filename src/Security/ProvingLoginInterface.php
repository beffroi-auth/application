<?php

declare(strict_types=1);

namespace App\Security;

use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

interface ProvingLoginInterface
{
    /**
     * Null when the passport did not come from this login.
     *
     * @return non-empty-list<AuthenticationMethod>|null
     */
    public function proofs(Passport $passport): ?array;

    /**
     * @return non-empty-list<AuthenticationMethod>
     */
    public function everyProvableMethod(): array;
}
