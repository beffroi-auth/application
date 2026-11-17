<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * beffroi/saml S-57
 */
interface DatesItsProofsInterface extends ProvingLoginInterface
{
    /**
     * Null when the passport is another login's.
     */
    public function provenAt(Passport $passport): ?DateTimeImmutable;
}
