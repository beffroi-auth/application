<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Application\Client\ClientInventory;
use Beffroi\Oidc\Domain\Client\InventoriedClient;
use function sprintf;
use Symfony\Component\Console\Style\SymfonyStyle;
use function trim;

final readonly class NamedClient
{
    public function __construct(
        private ClientInventory $clients,
    ) {
    }

    public function orTold(SymfonyStyle $io, Tenant $tenant, string $clientId): ?InventoriedClient
    {
        $named = trim($clientId);
        $client = $this->clients->find($tenant, $named);
        if ($client === null) {
            $io->error(sprintf('There is no client "%s" in the tenant "%s".', $named, $tenant->slug));

            return null;
        }

        return $client;
    }
}
