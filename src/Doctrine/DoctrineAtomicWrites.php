<?php

declare(strict_types=1);

namespace App\Doctrine;

use Beffroi\Core\Domain\Identity\LinkedIdentity;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\AtomicWritesInterface;
use Closure;
use LogicException;
use Throwable;

/**
 * The transaction of the connection and not wrapInTransaction() of the manager, which closes the manager when its
 * callback throws. A failure clears the manager, which the rollback leaves holding the objects it flushed.
 */
final readonly class DoctrineAtomicWrites implements AtomicWritesInterface
{
    public function __construct(
        private EntityManagers $managers,
    ) {
    }

    public function atomically(Closure $writes): mixed
    {
        $manager = $this->managers->forClass(User::class);
        $connection = $manager->getConnection();
        if ($this->managers->forClass(LinkedIdentity::class)->getConnection() !== $connection) {
            throw new LogicException('A person and the linked identity that names it are written through two connections, so no transaction can keep them together.');
        }

        try {
            return $connection->transactional($writes);
        } catch (Throwable $failure) {
            $manager->clear();

            throw $failure;
        }
    }
}
