<?php

declare(strict_types=1);

namespace App\Trust;

use DateTimeImmutable;
use function hash;
use App\Cache\SeenOnce;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Trust\TrustedIssuer;
use Beffroi\Oidc\Port\IssuerAssertionReplayGuardInterface;
use function sprintf;

/**
 * RFC 7523 §3
 *
 * Its pool is its own, so that a grant and a client authentication never satisfy each other's guard.
 */
final readonly class IssuerAssertionReplayGuard implements IssuerAssertionReplayGuardInterface
{
    public function __construct(
        private SeenOnce $seen,
    ) {
    }

    public function remember(Tenant $tenant, TrustedIssuer $issuer, string $jti, DateTimeImmutable $expiresAt): bool
    {
        return $this->seen->remember(self::key($tenant, $issuer, $jti), $expiresAt);
    }

    /**
     * A "jti" is unique per issuer only (RFC 7523 §3), and an issuer may serve two tenants.
     */
    private static function key(Tenant $tenant, TrustedIssuer $issuer, string $jti): string
    {
        return 'beffroi.issuer_assertions.' . hash('xxh128', sprintf('%s|%s|%s', $tenant->id->toRfc4122(), $issuer->issuer, $jti));
    }
}
