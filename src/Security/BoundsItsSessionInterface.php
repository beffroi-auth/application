<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * SAML Core §2.7.2, beffroi/saml S-57
 */
interface BoundsItsSessionInterface extends ProvingLoginInterface
{
    /**
     * Null when the passport is another login's or bounds nothing.
     */
    public function sessionNotOnOrAfter(Passport $passport): ?DateTimeImmutable;
}
