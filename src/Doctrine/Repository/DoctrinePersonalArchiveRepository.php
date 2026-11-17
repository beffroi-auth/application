<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Archive\PersonalArchive;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\PersonalArchiveRepositoryInterface;
use function count;
use DateTimeImmutable;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<PersonalArchive>
 */
final readonly class DoctrinePersonalArchiveRepository extends DoctrineRepository implements PersonalArchiveRepositoryInterface
{
    public const int FORGOTTEN_AT_ONCE = 100;

    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, PersonalArchive::class);
    }

    public function find(Tenant $tenant, Uuid $id): ?PersonalArchive
    {
        return $this->repository()->findOneBy([
            'id' => $id,
            'tenant' => $tenant,
        ]);
    }

    public function latestOf(User $user): ?PersonalArchive
    {
        /** @var PersonalArchive|null $archive */
        $archive = $this->queryBuilder('a')
            ->where('a.user = :user')
            ->orderBy('a.askedAt', SortDirection::Descending)
            ->addOrderBy('a.id', SortDirection::Descending)
            ->setMaxResults(1)
            ->setParameter('user', $user->id, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $archive;
    }

    public function save(PersonalArchive $archive): void
    {
        $this->store($archive);
    }

    public function forget(PersonalArchive $archive): void
    {
        $this->discard($archive);
    }

    public function forgetExpired(Tenant $tenant, DateTimeImmutable $moment): int
    {
        /** @var list<PersonalArchive> $expired */
        $expired = $this->queryBuilder('a')
            ->where('a.tenant = :tenant')
            ->andWhere('a.expiresAt <= :moment')
            ->orderBy('a.expiresAt', SortDirection::Ascending)
            ->setMaxResults(self::FORGOTTEN_AT_ONCE)
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->setParameter('moment', $moment)
            ->getQuery()
            ->getResult();
        if ($expired === []) {
            return 0;
        }
        $manager = $this->manager();
        foreach ($expired as $archive) {
            $manager->remove($archive);
        }
        $manager->flush();

        return count($expired);
    }
}
