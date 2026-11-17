<?php

declare(strict_types=1);

namespace App\Load;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Port\ClientLoadBudgetInterface;
use function sprintf;

/**
 * Counted by tenant and by client, as two tenants may register the same identifier; the tenant being a UUID, no
 * client identifier reaches across the separator into another tenant's count.
 */
final readonly class RateLimitedClientLoad implements ClientLoadBudgetInterface
{
    public function __construct(
        private LoadBudget $budget,
    ) {
    }

    public function consume(Tenant $tenant, string $clientId): void
    {
        $this->budget->consume(sprintf('%s|%s', $tenant->id->toRfc4122(), $clientId), $clientId);
    }
}
