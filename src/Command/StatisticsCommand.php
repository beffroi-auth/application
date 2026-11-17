<?php

declare(strict_types=1);

namespace App\Command;

use App\Statistics\InstanceStatistics;
use function array_keys;
use function array_map;
use function array_values;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What the instance knows of itself now (decision 0040), the same numbers the administration API answers: people,
 * clients and open sessions of the tenant, its keys by state, its acts by name over what the journal keeps, and what
 * waits in each lane of the worker. Reads and writes nothing, and keeps nothing of what it read.
 */
#[AsCommand(
    name: self::NAME,
    description: 'Say what the instance knows of itself now: its people, clients and open sessions, its keys by state, its acts by name over what the journal keeps, and the messages waiting in each lane. Reads and writes nothing.',
)]
final readonly class StatisticsCommand
{
    public const string NAME = 'beffroi:statistics';

    public function __construct(
        private ConfiguredTenant $tenant,
        private InstanceStatistics $statistics,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $statistics = $this->statistics->of($tenant);
        $io->definitionList(
            ['Read at' => $statistics->until->format('Y-m-d H:i:s')],
            ['Journal kept since' => $statistics->since->format('Y-m-d H:i:s')],
            ['People' => (string) $statistics->users],
            ['Clients' => (string) $statistics->clients],
            ['Open sessions' => (string) $statistics->openSessions],
        );
        $io->section('Keys');
        $io->table(['State', 'Keys'], array_map(
            static fn (string $state, int $count): array => [$state, (string) $count],
            array_keys($statistics->keys),
            array_values($statistics->keys),
        ));
        $io->section(sprintf('Acts since %s', $statistics->since->format('Y-m-d')));
        if ($statistics->acts === []) {
            $io->text('The journal holds no act.');
        } else {
            $io->table(['Act', 'Count'], array_map(
                static fn (string $act, int $count): array => [$act, (string) $count],
                array_keys($statistics->acts),
                array_values($statistics->acts),
            ));
        }
        $io->section('Lanes');
        $io->table(['Transport', 'Waiting'], array_map(
            static fn (string $transport, ?int $messages): array => [$transport, $messages === null ? 'cannot say' : (string) $messages],
            array_keys($statistics->backlogs),
            array_values($statistics->backlogs),
        ));

        return Command::SUCCESS;
    }
}
