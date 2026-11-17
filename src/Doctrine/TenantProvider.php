<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Tenant\TenantFactory;
use Beffroi\Core\Domain\Tenant\PasskeyAttestation;
use Beffroi\Core\Domain\Tenant\SelfServedClaims;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Domain\Tenant\TenantLook;
use Beffroi\Core\Port\AmbientTenantInterface;
use function count;
use function implode;
use function sprintf;
use Symfony\Contracts\Service\ResetInterface;

final class TenantProvider implements AmbientTenantInterface, ResetInterface
{
    private ?Tenant $tenant = null;

    public function __construct(
        private readonly TenantFactory $factory,
        private readonly EntityManagers $managers,
    ) {
    }

    /**
     * @throws TenantNotSynchronizedException
     */
    public function provide(): Tenant
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }
        $configured = $this->factory->create();
        $tenant = $this->managers->forClass(Tenant::class)->find(Tenant::class, $configured->id);
        if ($tenant === null) {
            throw TenantNotSynchronizedException::missing($configured->slug);
        }
        if (! $tenant->issuer->equals($configured->issuer)) {
            throw TenantNotSynchronizedException::differs($configured->slug, 'issuer', (string) $tenant->issuer, (string) $configured->issuer);
        }
        if ($tenant->name !== $configured->name) {
            throw TenantNotSynchronizedException::differs($configured->slug, 'name', $tenant->name, $configured->name);
        }
        if (! $tenant->authenticationContextPolicy->equals($configured->authenticationContextPolicy)) {
            throw TenantNotSynchronizedException::differs(
                $configured->slug,
                'authentication_classes',
                implode(' ', $tenant->authenticationContextPolicy->names()),
                implode(' ', $configured->authenticationContextPolicy->names()),
            );
        }

        if (! $tenant->selfServedClaims->equals($configured->selfServedClaims)) {
            throw TenantNotSynchronizedException::differs(
                $configured->slug,
                'self_served_claims',
                self::describedClaims($tenant->selfServedClaims),
                self::describedClaims($configured->selfServedClaims),
            );
        }
        if (! $tenant->look->equals($configured->look)) {
            throw TenantNotSynchronizedException::differs($configured->slug, 'look', self::described($tenant->look), self::described($configured->look));
        }
        if (! $tenant->passkeyAttestation->equals($configured->passkeyAttestation)) {
            throw TenantNotSynchronizedException::differs(
                $configured->slug,
                'passkey_attestation',
                self::describedAttestation($tenant->passkeyAttestation),
                self::describedAttestation($configured->passkeyAttestation),
            );
        }

        return $this->tenant = $tenant;
    }

    private static function describedClaims(SelfServedClaims $claims): string
    {
        return $claims->names === [] ? 'none' : implode(' ', $claims->names);
    }

    private static function describedAttestation(PasskeyAttestation $attestation): string
    {
        return sprintf(
            '%s, %d root(s), %d own root(s), %d model(s) under alert',
            $attestation->policy->name(),
            count($attestation->roots),
            count($attestation->ownRoots),
            count($attestation->modelsUnderAlert),
        );
    }

    private static function described(TenantLook $look): string
    {
        if (! $look->isDeclared()) {
            return 'none';
        }
        $parts = [];
        if ($look->logoUri !== null) {
            $parts[] = 'logo ' . $look->logoUri->value;
        }
        if ($look->primaryColor !== null) {
            $parts[] = sprintf('%s on %s', $look->primaryColor, (string) $look->primaryContentColor);
        }

        return implode(', ', $parts);
    }

    public function reset(): void
    {
        $this->tenant = null;
    }

    public function onClear(): void
    {
        $this->reset();
    }
}
