<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Group\Group;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\GroupAlreadyExistsException;
use Beffroi\Core\Port\GroupRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @extends DoctrineRepository<Group>
 */
final readonly class DoctrineGroupRepository extends DoctrineRepository implements GroupRepositoryInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, Group::class);
    }

    public function findByName(Tenant $tenant, string $name): ?Group
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'name' => $name,
        ]);
    }

    public function save(Group $group): void
    {
        try {
            $this->store($group);
        } catch (UniqueConstraintViolationException $exception) {
            throw GroupAlreadyExistsException::named($group->name, $exception);
        }
    }
}
