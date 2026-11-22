<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Confirmation\AddressConfirmation;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\AddressConfirmationRepositoryInterface;
use Beffroi\Core\Port\DeadRecordsInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * The confirmations of one kind of address, each in the table of its kind, and the sweep of those spent or out of
 * time.
 *
 * @template T of AddressConfirmation
 *
 * @extends DoctrineRepository<T>
 */
abstract readonly class DoctrineAddressConfirmationRepository extends DoctrineRepository implements AddressConfirmationRepositoryInterface, DeadRecordsInterface
{
    /**
     * @param class-string<T> $class
     */
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
        private string $class,
    ) {
        parent::__construct($managers, $class);
    }

    public function find(Uuid $id): ?AddressConfirmation
    {
        return $this->repository()->find($id);
    }

    public function unspentOf(User $user): array
    {
        return $this->repository()->findBy([
            'user' => $user,
            'spentAt' => null,
        ], [
            'createdAt' => 'ASC',
            'id' => 'ASC',
        ]);
    }

    public function save(AddressConfirmation $confirmation): void
    {
        $this->store($confirmation);
    }

    public function forget(AddressConfirmation $confirmation): void
    {
        $this->discard($confirmation);
    }

    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        return $this->sweep->spentOrOutOfTime($this->class, $tenant, $now, $limit);
    }
}
