<?php

declare(strict_types=1);

namespace App\Security;

use DateTimeImmutable;
use Beffroi\Core\Domain\Session\RequestContext;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

interface DatesItsCredentialInterface extends ProvingLoginInterface
{
    /**
     * @return bool false when the passport is another login's
     */
    public function recordUse(Passport $passport, SecurityUser $user, DateTimeImmutable $now, RequestContext $context): bool;
}
