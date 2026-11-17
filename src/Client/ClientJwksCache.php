<?php

declare(strict_types=1);

namespace App\Client;

use DateInterval;
use DateTimeImmutable;
use function hash;
use Beffroi\Oidc\Domain\Client\CachedClientJwks;
use Beffroi\Oidc\Domain\Client\ClientJwks;
use Beffroi\Oidc\Domain\Client\ClientUri;
use Beffroi\Oidc\Port\ClientJwksCacheInterface;
use function is_array;
use function is_int;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Stores plain values, not objects, read back as strictly as from the party itself: a pool outlives a deployment.
 */
final readonly class ClientJwksCache implements ClientJwksCacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private DateInterval $lifetime,
    ) {
    }

    public function read(ClientUri $jwksUri): ?CachedClientJwks
    {
        $item = $this->cache->getItem(self::key($jwksUri));
        if (! $item->isHit()) {
            return null;
        }
        $kept = $item->get();
        if (! is_array($kept) || ! is_array($kept['keys'] ?? null) || ! is_int($kept['fetched_at'] ?? null)) {
            return null;
        }
        try {
            $keys = ClientJwks::fromDocument([
                'keys' => $kept['keys'],
            ]);
        } catch (Throwable) {
            return null;
        }

        return new CachedClientJwks($keys, new DateTimeImmutable('@' . $kept['fetched_at']));
    }

    public function write(ClientUri $jwksUri, CachedClientJwks $keys): void
    {
        $item = $this->cache->getItem(self::key($jwksUri));
        $item->set([
            'keys' => $keys->keys->keys,
            'fetched_at' => $keys->fetchedAt->getTimestamp(),
        ]);
        $item->expiresAfter($this->lifetime);
        $this->cache->save($item);
    }

    /**
     * Hashed: a PSR-6 key accepts no arbitrary character.
     */
    private static function key(ClientUri $jwksUri): string
    {
        return 'beffroi.published_keys.' . hash('xxh128', $jwksUri->value);
    }
}
