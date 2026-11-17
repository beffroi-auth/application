<?php

declare(strict_types=1);

namespace App\Dpop;

use DateInterval;
use DateTimeImmutable;
use function hash;
use Beffroi\Oidc\Application\Secret\SecretDrawer;
use Beffroi\Oidc\Exception\UseDpopNonceException;
use Beffroi\Oidc\Port\DpopNonceInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * RFC 9449 §8
 *
 * A nonce stands for its whole lifetime: a replay is refused by the "jti" of each proof, remembered on its own.
 */
final readonly class CachedDpopNonce implements DpopNonceInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private SecretDrawer $secrets,
        private DateInterval $lifetime,
    ) {
    }

    public function verify(?string $nonce, DateTimeImmutable $now): void
    {
        if ($nonce !== null && $this->cache->getItem(self::key($nonce))->isHit()) {
            return;
        }

        throw new UseDpopNonceException(
            $this->issue($now),
            $nonce === null
                ? 'This provider asks a DPoP proof to carry a nonce it issued (RFC 9449 section 8).'
                : 'The DPoP proof carries a nonce this provider no longer accepts (RFC 9449 section 8).',
        );
    }

    private function issue(DateTimeImmutable $now): string
    {
        $nonce = $this->secrets->draw();
        $item = $this->cache->getItem(self::key($nonce));
        $item->set($now->getTimestamp());
        $item->expiresAt($now->add($this->lifetime));
        $this->cache->save($item);

        return $nonce;
    }

    private static function key(string $nonce): string
    {
        return 'beffroi.dpop_nonces.' . hash('xxh128', $nonce);
    }
}
