<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Domain\Ticket\Ticket;
use Beffroi\Core\Port\DeadRecordsInterface;
use Beffroi\Core\Port\TicketRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use function sprintf;
use Symfony\Component\Uid\Uuid;

/**
 * The tickets of one kind, each in the table of its kind (decision 0026), and the sweep of those spent or out of time
 * (issue #177).
 *
 * @template T of Ticket
 *
 * @extends DoctrineRepository<T>
 *
 * @implements TicketRepositoryInterface<T>
 */
abstract readonly class DoctrineTicketRepository extends DoctrineRepository implements DeadRecordsInterface, TicketRepositoryInterface
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

    public function find(Uuid $id): ?Ticket
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

    public function save(Ticket $ticket): void
    {
        $this->store($ticket);
    }

    public function forget(Ticket $ticket): void
    {
        $this->discard($ticket);
    }

    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column($this->class, 'expiresAt');
        $spent = $this->sweep->column($this->class, 'spentAt');

        return $this->sweep->from(
            $this->class,
            $tenant,
            sprintf('(%s <= :dead OR %s IS NOT NULL)', $expiry, $spent),
            $expiry,
            [
                'dead' => $now,
            ],
            [
                'dead' => Types::DATETIME_IMMUTABLE,
            ],
            $limit,
        );
    }
}
