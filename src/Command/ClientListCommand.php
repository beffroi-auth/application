<?php

declare(strict_types=1);

namespace App\Command;

use function array_map;
use DateTimeImmutable;
use Beffroi\Oidc\Application\Client\ClientInventory;
use Beffroi\Oidc\Domain\Client\InventoriedClient;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'List the clients of the tenant: where each came from, when it was registered and when it was last used.',
)]
final readonly class ClientListCommand
{
    public const string NAME = 'beffroi:client:list';

    public function __construct(
        private ConfiguredTenant $tenant,
        private ClientInventory $clients,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $clients = [...$this->clients->inventoryIn($tenant)];
        if ($clients === []) {
            $io->text(sprintf('The tenant "%s" has no client.', $tenant->slug));

            return Command::SUCCESS;
        }

        $io->table(
            ['Client id', 'Name', 'Source', 'Registered', 'Last used'],
            array_map(static fn (InventoriedClient $client): array => [
                $client->clientId,
                $client->clientName ?? '',
                $client->source,
                self::date($client->registeredAt),
                self::date($client->lastUsedAt),
            ], $clients),
        );

        return Command::SUCCESS;
    }

    private static function date(?DateTimeImmutable $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }
}
