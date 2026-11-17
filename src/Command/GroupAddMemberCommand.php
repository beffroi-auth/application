<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Core\Application\Group\LocalMemberships;
use Beffroi\Core\Exception\GroupMembershipException;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A person of the tenant made a member of one of its groups, the tenant holding the membership itself (decision
 * 0002): what makes somebody an administrator when the group is one the deployment names (decision 0038). A group a
 * directory fills by SCIM takes its members from that directory alone.
 */
#[AsCommand(
    name: 'beffroi:group:add-member',
    description: 'Make a user a member of a group of the tenant, a membership the tenant holds itself.',
)]
final readonly class GroupAddMemberCommand
{
    public function __construct(
        private ConfiguredTenant $tenant,
        private NamedUser $namedUsers,
        private NamedGroup $namedGroups,
        private LocalMemberships $memberships,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the group, exactly as the tenant holds it.')]
        string $group,
        #[Argument(description: 'The username of the user.')]
        string $username,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $user = $this->namedUsers->orTold($io, $tenant, $username);
        if ($user === null) {
            return Command::FAILURE;
        }
        $named = $this->namedGroups->orTold($io, $tenant, $group);
        if ($named === null) {
            return Command::FAILURE;
        }

        try {
            $this->memberships->add($named, $user);
        } catch (GroupMembershipException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('"%s" is a member of the group "%s".', $user->username, $named->name));

        return Command::SUCCESS;
    }
}
