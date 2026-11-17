<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Core\Exception\GroupAlreadyExistsException;
use Beffroi\Core\Application\Group\GroupProvisioner;
use Beffroi\Core\Exception\InvalidArgumentException;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:group:create',
    description: 'Create a group of the tenant, which the declaration of an upstream provider may map the groups it asserts to.',
)]
final readonly class GroupCreateCommand
{
    public function __construct(
        private ConfiguredTenant $tenant,
        private GroupProvisioner $provisioner,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the group; unique in the tenant.')]
        string $name,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        try {
            $group = $this->provisioner->create($tenant, $name);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (GroupAlreadyExistsException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            [
                'Name' => $group->name,
            ],
            [
                'Source' => $group->source->value,
            ],
            [
                'Identifier' => $group->id->toRfc4122(),
            ],
            [
                'Tenant' => $tenant->slug,
            ],
        );
        $io->success(sprintf('The group "%s" is created.', $group->name));

        return Command::SUCCESS;
    }
}
