<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Messenger\LaneBacklogs;
use Beffroi\Core\Application\Journal\JournalRetention;
use Beffroi\Core\Domain\Key\KeyStatus;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\CanCountActiveSessionsInterface;
use Beffroi\Core\Port\CanCountActsInterface;
use Beffroi\Core\Port\CanCountUsersInterface;
use Beffroi\Core\Port\TenantKeyRepositoryInterface;
use Beffroi\Oidc\Application\Client\ClientInventory;
use Psr\Clock\ClockInterface;

/**
 * What an instance knows of itself, read when it is asked and from what it already holds (decision 0040): the numbers
 * of its tenant, its keys by state, its acts by name over the window its journal keeps (`JournalRetention`) and never
 * beyond it, and the backlog of each lane of its worker. Nothing is kept of a reading, nothing is sent anywhere and
 * nothing raises an alert: a history of these numbers, an export of them and an alert on them are what operates an
 * instance (decision 0001). `beffroi:statistics` and the administration API read the same.
 *
 * It is here and not in a package because it reads both packages and the worker at once, as the integration does.
 */
final readonly class InstanceStatistics
{
    public function __construct(
        private CanCountUsersInterface $users,
        private CanCountActiveSessionsInterface $sessions,
        private CanCountActsInterface $acts,
        private TenantKeyRepositoryInterface $keys,
        private ClientInventory $clients,
        private JournalRetention $retention,
        private LaneBacklogs $lanes,
        private ClockInterface $clock,
    ) {
    }

    public function of(Tenant $tenant): Statistics
    {
        $now = $this->clock->now();
        $since = $this->retention->keptSince();
        $keys = [];
        foreach (KeyStatus::cases() as $status) {
            $keys[$status->value] = 0;
        }
        foreach ($this->keys->findAll($tenant) as $key) {
            ++$keys[$key->status->value];
        }
        $clients = 0;
        foreach ($this->clients->inventoryIn($tenant) as $_) {
            ++$clients;
        }

        return new Statistics(
            $since,
            $now,
            $this->users->countUsersIn($tenant),
            $clients,
            $this->sessions->countActiveSessionsIn($tenant, $now),
            $keys,
            $this->acts->countActsSince($tenant, $since),
            $this->lanes->backlogs(),
        );
    }
}
