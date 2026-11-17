<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\TenantRepositoryInterface;

final readonly class DoctrineTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private EntityManagers $managers,
    ) {
    }

    public function save(Tenant $tenant): void
    {
        $manager = $this->managers->forClass(Tenant::class);
        $manager->persist($tenant);
        $manager->flush();
    }
}
