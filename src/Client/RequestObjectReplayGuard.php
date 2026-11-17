<?php

declare(strict_types=1);

namespace App\Client;

use DateTimeImmutable;
use function hash;
use App\Cache\SeenOnce;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Port\RequestObjectReplayGuardInterface;
use function sprintf;

/**
 * CIBA Core §7.1.1
 */
final readonly class RequestObjectReplayGuard implements RequestObjectReplayGuardInterface
{
    public function __construct(
        private SeenOnce $seen,
    ) {
    }

    public function remember(Client $client, string $jti, DateTimeImmutable $expiresAt): bool
    {
        return $this->seen->remember(self::key($client, $jti), $expiresAt);
    }

    private static function key(Client $client, string $jti): string
    {
        return 'beffroi.request_objects.' . hash('xxh128', sprintf('%s|%s|%s', $client->tenant->id->toRfc4122(), $client->registration->clientId, $jti));
    }
}
