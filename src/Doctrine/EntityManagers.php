<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use function sprintf;

/**
 * Asked of the registry at every call and never held: a manager closed by a failed flush is replaced there.
 */
final readonly class EntityManagers
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {
    }

    /**
     * @param class-string $class
     *
     * @throws LogicException
     */
    public function forClass(string $class): EntityManagerInterface
    {
        $manager = $this->registry->getManagerForClass($class);
        if (! $manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('No entity manager maps "%s".', $class));
        }

        return $manager;
    }

    /**
     * @return array<string, ObjectManager>
     */
    public function all(): array
    {
        return $this->registry->getManagers();
    }
}
