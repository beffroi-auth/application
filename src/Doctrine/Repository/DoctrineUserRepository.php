<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use App\Doctrine\PersonalData\BlindIndexedColumns;
use App\Doctrine\PersonalData\TenantBlindIndexes;
use Beffroi\Core\Application\Identity\UserAlreadyExistsException;
use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Identity\Subject;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\CanCountUsersInterface;
use Beffroi\Core\Port\UserRepositoryInterface;
use function array_values;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<User>
 */
final readonly class DoctrineUserRepository extends DoctrineRepository implements UserRepositoryInterface, CanCountUsersInterface
{
    public function __construct(
        EntityManagers $managers,
        private TenantBlindIndexes $indexes,
    ) {
        parent::__construct($managers, User::class);
    }

    public function find(Uuid $id): ?User
    {
        return $this->repository()->findOneBy([
            'id' => $id,
        ]);
    }

    public function findBySubject(Tenant $tenant, Subject $subject): ?User
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'subject' => $subject,
        ]);
    }

    public function findByUsername(Tenant $tenant, string $username): ?User
    {
        $column = BlindIndexedColumns::of(User::class, 'username');

        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            $column->tag => $this->indexes->of($column)->of($username),
        ]);
    }

    public function findAllByEmail(Tenant $tenant, EmailAddress $email): array
    {
        $column = BlindIndexedColumns::of(User::class, 'email');

        return array_values($this->repository()->findBy([
            'tenant' => $tenant,
            $column->tag => $this->indexes->of($column)->of($email->value),
        ]));
    }

    /**
     * beffroi/saml S-56
     *
     * @throws UserAlreadyExistsException
     */
    public function countUsersIn(Tenant $tenant): int
    {
        return (int) $this->queryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.tenant = :tenant')
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(User $user): void
    {
        try {
            $this->store($user);
        } catch (UniqueConstraintViolationException $exception) {
            throw UserAlreadyExistsException::writtenMeanwhile($exception);
        }
    }
}
