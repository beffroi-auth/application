<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Tenant\Tenant;

/**
 * What the templates are given of a tenant, never the entity, which carries its users and its clients.
 */
final readonly class TenantPresentation
{
    private function __construct(
        public string $name,
        public ?string $logoUri = null,
        public ?string $primaryColor = null,
        public ?string $primaryContentColor = null,
    ) {
    }

    public static function of(Tenant $tenant): self
    {
        return new self(
            $tenant->name,
            $tenant->look->logoUri?->value,
            $tenant->look->primaryColor,
            $tenant->look->primaryContentColor,
        );
    }
}
