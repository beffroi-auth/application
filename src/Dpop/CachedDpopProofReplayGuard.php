<?php

declare(strict_types=1);

namespace App\Dpop;

use DateTimeImmutable;
use function hash;
use Beffroi\Oidc\Domain\Token\KeyConfirmation;
use Beffroi\Oidc\Port\DpopProofReplayGuardInterface;
use App\Cache\SeenOnce;
use function sprintf;

/**
 * RFC 9449 §11.1
 */
final readonly class CachedDpopProofReplayGuard implements DpopProofReplayGuardInterface
{
    public function __construct(
        private SeenOnce $seen,
    ) {
    }

    public function remember(KeyConfirmation $key, string $jti, DateTimeImmutable $expiresAt): bool
    {
        return $this->seen->remember(self::key($key, $jti), $expiresAt);
    }

    /**
     * Per key: it is the holder of a key that chooses the "jti" of its proofs, and two clients may
     * choose the same one.
     */
    private static function key(KeyConfirmation $key, string $jti): string
    {
        return 'beffroi.dpop_proofs.' . hash('xxh128', sprintf('%s|%s', $key->thumbprint, $jti));
    }
}
