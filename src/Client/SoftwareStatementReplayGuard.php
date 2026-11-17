<?php

declare(strict_types=1);

namespace App\Client;

use DateTimeImmutable;
use function hash;
use App\Cache\SeenOnce;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Client\Registration\StatementIssuer;
use Beffroi\Oidc\Port\SoftwareStatementReplayGuardInterface;
use function sprintf;

/**
 * RFC 7591 §2.3
 *
 * Its pool is its own: a statement remembered here must not satisfy the guard of an assertion, nor the reverse.
 */
final readonly class SoftwareStatementReplayGuard implements SoftwareStatementReplayGuardInterface
{
    public function __construct(
        private SeenOnce $seen,
    ) {
    }

    public function remember(Tenant $tenant, StatementIssuer $issuer, string $jti, DateTimeImmutable $expiresAt): bool
    {
        return $this->seen->remember(self::key($tenant, $issuer, $jti), $expiresAt);
    }

    private static function key(Tenant $tenant, StatementIssuer $issuer, string $jti): string
    {
        return 'beffroi.software_statements.' . hash('xxh128', sprintf('%s|%s|%s', $tenant->id->toRfc4122(), $issuer->issuer, $jti));
    }
}
