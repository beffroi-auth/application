<?php

declare(strict_types=1);

namespace App\Statistics;

use DateTimeImmutable;

/**
 * What an instance knows of itself at a moment (decision 0040): how many people, clients and open sessions its tenant
 * has, its signing keys by state, the acts its journal holds by name over what the journal keeps, and what waits in
 * each lane of its worker. Numbers only: nothing of anybody.
 */
final readonly class Statistics
{
    /**
     * @param DateTimeImmutable          $since    the oldest moment the journal still holds an act of
     * @param DateTimeImmutable          $until    the moment the numbers were read
     * @param array<string, int>         $keys     the number of keys in each state, by the name of the state
     * @param array<string, int>         $acts     the number of acts of each name since $since, the most frequent first
     * @param array<string, int|null>    $backlogs the messages waiting in each lane, null for a lane that cannot say
     */
    public function __construct(
        public DateTimeImmutable $since,
        public DateTimeImmutable $until,
        public int $users,
        public int $clients,
        public int $openSessions,
        public array $keys,
        public array $acts,
        public array $backlogs,
    ) {
    }
}
