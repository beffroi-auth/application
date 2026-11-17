<?php

declare(strict_types=1);

namespace App\Command;

use DateTimeImmutable;
use Beffroi\Oidc\Domain\Client\InventoriedClient;
use Beffroi\Oidc\Port\ClientStoreInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'Show a client of the tenant: where it came from, its dates, and what it registered.',
)]
final readonly class ClientShowCommand
{
    public const string NAME = 'beffroi:client:show';

    public function __construct(
        private ConfiguredTenant $tenant,
        private NamedClient $namedClients,
        private ClientStoreInterface $store,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier of the client, as "beffroi:client:list" shows it.')]
        string $clientId,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $client = $this->namedClients->orTold($io, $tenant, $clientId);
        if ($client === null) {
            return Command::FAILURE;
        }

        $io->definitionList(...self::identity($client));

        $record = $this->store->findRecord($tenant, $client->clientId);
        if ($record === null) {
            $io->text(sprintf(
                'This client is declared in the configuration of the deployment; what it is, is what "beffroi.yaml" says under "%s".',
                $client->clientId,
            ));

            return Command::SUCCESS;
        }

        self::members($io, 'Registered', $record->metadata);
        self::members($io, 'Assigned by the registration policy', $record->assigned);
        self::members($io, 'Given by the initial access token', $record->granted ?? []);

        return Command::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function identity(InventoriedClient $client): array
    {
        return [
            [
                'Client id' => $client->clientId,
            ],
            [
                'Name' => $client->clientName ?? '',
            ],
            [
                'Source' => $client->source,
            ],
            [
                'Registered' => self::date($client->registeredAt),
            ],
            [
                'Last used' => self::date($client->lastUsedAt),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $members
     */
    private static function members(SymfonyStyle $io, string $section, array $members): void
    {
        if ($members === []) {
            return;
        }
        $io->section($section);
        $io->table(['Member', 'Value'], PrintedMembers::rows($members));
    }

    private static function date(?DateTimeImmutable $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }
}
