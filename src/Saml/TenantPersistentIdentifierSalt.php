<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Port\PairwiseSaltInterface;
use Beffroi\Saml\Port\PersistentIdentifierSaltInterface;

/**
 * beffroi/saml S-13
 *
 * The secret of the pairwise subjects of OpenID Connect, hashed with an entity ID here and with the host of a
 * sector there, so that no identifier of one is an identifier of the other.
 */
final readonly class TenantPersistentIdentifierSalt implements PersistentIdentifierSaltInterface
{
    public function __construct(
        private PairwiseSaltInterface $salts,
    ) {
    }

    public function saltOf(Tenant $tenant): string
    {
        return $this->salts->saltOf($tenant);
    }
}
