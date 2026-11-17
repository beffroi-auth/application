<?php

declare(strict_types=1);

namespace App\Client;

use DateTimeImmutable;
use function hash;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Port\ClientAssertionReplayGuardInterface;
use App\Cache\SeenOnce;
use function sprintf;

/**
 * RFC 7523 §3, §4
 */
final readonly class ClientAssertionReplayGuard implements ClientAssertionReplayGuardInterface
{
    public function __construct(
        private SeenOnce $seen,
    ) {
    }

    public function remember(Client $client, string $jti, DateTimeImmutable $expiresAt): bool
    {
        return $this->seen->remember(self::key($client, $jti), $expiresAt);
    }

    /**
     * Per tenant and per client: two clients may use the same "jti" (RFC 7523 §3).
     */
    private static function key(Client $client, string $jti): string
    {
        return 'beffroi.client_assertions.' . hash('xxh128', sprintf('%s|%s|%s', $client->tenant->id->toRfc4122(), $client->registration->clientId, $jti));
    }
}
