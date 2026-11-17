<?php

declare(strict_types=1);

namespace App\Cache;

use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A pool cannot add atomically: two identical identifiers racing on the same millisecond may both be taken.
 */
final readonly class SeenOnce
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return bool true when it had not been seen, false when it had
     */
    public function remember(string $key, DateTimeImmutable $expiresAt): bool
    {
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return false;
        }
        $item->set($expiresAt->getTimestamp());
        $item->expiresAt($expiresAt);
        $this->cache->save($item);

        return true;
    }
}
