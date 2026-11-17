<?php

declare(strict_types=1);

namespace App\Saml;

use function array_fill_keys;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Authorization\ClaimRequest;
use Beffroi\Oidc\Port\ClaimsProviderInterface;
use Beffroi\Saml\Port\ServedClaimsInterface;

/**
 * beffroi/saml S-16
 */
final readonly class ProviderServedClaims implements ServedClaimsInterface
{
    public function __construct(
        private ClaimsProviderInterface $claims,
    ) {
    }

    public function names(): array
    {
        return $this->claims->supportedClaims();
    }

    public function of(User $user, array $names): array
    {
        return $this->claims->claims($user, null, array_fill_keys($names, new ClaimRequest()));
    }
}
