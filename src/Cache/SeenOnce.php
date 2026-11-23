<?php

declare(strict_types=1);

namespace App\Cache;

use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Read and written under a lock named after the key, from the store every process of the deployment shares: a pool
 * cannot add atomically, and of two requests presenting one identifier at once, one takes it.
 */
final readonly class SeenOnce
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private LockFactory $locks,
    ) {
    }

    /**
     * @return bool true when it had not been seen, false when it had
     */
    public function remember(string $key, DateTimeImmutable $expiresAt): bool
    {
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);
        try {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                return false;
            }
            $item->set($expiresAt->getTimestamp());
            $item->expiresAt($expiresAt);
            $this->cache->save($item);

            return true;
        } finally {
            $lock->release();
        }
    }
}
