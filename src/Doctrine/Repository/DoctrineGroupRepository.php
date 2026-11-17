<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Group\Group;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\GroupAlreadyExistsException;
use Beffroi\Core\Port\GroupRepositoryInterface;
use Beffroi\Core\Port\GroupsOfATenantInterface;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * @extends DoctrineRepository<Group>
 */
final readonly class DoctrineGroupRepository extends DoctrineRepository implements GroupRepositoryInterface, GroupsOfATenantInterface
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

    public function of(Tenant $tenant): array
    {
        /** @var list<Group> $groups */
        $groups = $this->queryBuilder('g')
            ->where('g.tenant = :tenant')
            ->orderBy('g.name', SortDirection::Ascending)
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->getQuery()
            ->getResult();

        return $groups;
    }

    public function find(Tenant $tenant, Uuid $id): ?Group
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'id' => $id,
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
