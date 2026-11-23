<?php

declare(strict_types=1);

namespace App\Client;

use function array_key_exists;
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
        // A hash names the entry, not the URL: an entry that names another URL is none of this one.
        if (! is_array($kept) || ($kept['url'] ?? null) !== $jwksUri->value || ! is_int($kept['read_at'] ?? null)) {
            return null;
        }
        if (! array_key_exists('keys', $kept)) {
            return null;
        }
        $readAt = new DateTimeImmutable('@' . $kept['read_at']);
        if ($kept['keys'] === null) {
            return new CachedClientJwks(null, $readAt);
        }
        if (! is_array($kept['keys'])) {
            return null;
        }
        try {
            $keys = ClientJwks::fromDocument([
                'keys' => $kept['keys'],
            ]);
        } catch (Throwable) {
            return null;
        }

        return new CachedClientJwks($keys, $readAt);
    }

    public function write(ClientUri $jwksUri, CachedClientJwks $keys): void
    {
        $item = $this->cache->getItem(self::key($jwksUri));
        $item->set([
            'url' => $jwksUri->value,
            'keys' => $keys->keys?->keys,
            'read_at' => $keys->readAt->getTimestamp(),
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
