<?php

declare(strict_types=1);

namespace App\Saml;

use function array_map;
use Beffroi\Core\Domain\Identity\UpstreamProvider;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\UpstreamProvidersInterface;
use Beffroi\Saml\Domain\Upstream\UpstreamIdentityProvider;
use Beffroi\Saml\Port\UpstreamIdentityProvidersInterface;

/**
 * beffroi/saml S-60
 */
final readonly class SamlUpstreamProviders implements UpstreamProvidersInterface
{
    public function __construct(
        private UpstreamIdentityProvidersInterface $identityProviders,
    ) {
    }

    public function of(Tenant $tenant): array
    {
        return array_map(
            static fn (UpstreamIdentityProvider $identityProvider): UpstreamProvider => $identityProvider->provider(),
            $this->identityProviders->of($tenant),
        );
    }
}
