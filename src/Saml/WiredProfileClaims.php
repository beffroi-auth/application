<?php

declare(strict_types=1);

namespace App\Saml;

use App\Identity\WiredClaims;
use Beffroi\Saml\Port\ProfileClaimsInterface;

/**
 * beffroi/saml S-45
 */
final readonly class WiredProfileClaims implements ProfileClaimsInterface
{
    public function __construct(
        private WiredClaims $claims,
    ) {
    }

    public function names(): array
    {
        return $this->claims->names();
    }
}
