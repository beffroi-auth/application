<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Core\Domain\Group\Group;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\GroupRepositoryInterface;
use function sprintf;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The group a command names on its command line, of the tenant it works on. The name is read exactly, as the mapping
 * of a provider and the configuration name a group (GroupProvisioner), and a name no group carries is said the same way
 * by every command that takes one.
 */
final readonly class NamedGroup
{
    public function __construct(
        private GroupRepositoryInterface $groups,
    ) {
    }

    /**
     * @return Group|null null when the command was told there is no such group, and should fail
     */
    public function orTold(SymfonyStyle $io, Tenant $tenant, string $name): ?Group
    {
        $group = $this->groups->findByName($tenant, $name);
        if ($group === null) {
            $io->error(sprintf('There is no group named "%s" in the tenant "%s".', $name, $tenant->slug));

            return null;
        }

        return $group;
    }
}
