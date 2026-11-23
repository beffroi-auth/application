<?php

declare(strict_types=1);

namespace App\Lock;

use Beffroi\Core\Port\ExclusiveSectionsInterface;
use Closure;
use Symfony\Component\Lock\LockFactory;

/**
 * Under the locks every process of the deployment shares (config/packages/lock.yaml).
 */
final readonly class LockedSections implements ExclusiveSectionsInterface
{
    public function __construct(
        private LockFactory $locks,
    ) {
    }

    public function exclusively(string $name, Closure $section): mixed
    {
        $lock = $this->locks->createLock($name);
        $lock->acquire(true);
        try {
            return $section();
        } finally {
            $lock->release();
        }
    }
}
