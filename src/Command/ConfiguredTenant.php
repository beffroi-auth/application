<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use Beffroi\Core\Domain\Tenant\Tenant;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ConfiguredTenant
{
    public function __construct(
        private TenantProvider $tenants,
    ) {
    }

    public function orTold(SymfonyStyle $io): ?Tenant
    {
        try {
            return $this->tenants->provide();
        } catch (TenantNotSynchronizedException $exception) {
            $io->error([PrintedText::inBlock($exception->getMessage()), 'Run "beffroi:tenant:synchronize" first.']);

            return null;
        }
    }
}
