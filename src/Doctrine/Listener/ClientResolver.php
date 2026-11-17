<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Beffroi\Oidc\Domain\Client\ResolvesItsClientInterface;
use Beffroi\Oidc\Port\ClientRepositoryInterface;
use ReflectionProperty;

/**
 * Sets the client of a loaded row; a client no longer registered leaves the property uninitialised.
 */
final readonly class ClientResolver
{
    public function __construct(
        private ClientRepositoryInterface $clients,
    ) {
    }

    public function postLoad(ResolvesItsClientInterface $entity): void
    {
        $client = $this->clients->findByClientId($entity->tenant, $entity->clientId);
        if ($client === null) {
            return;
        }
        $holder = $entity->clientHolder();
        self::clientProperty($holder)->setValue($holder, $client);
    }

    public static function isResolved(ResolvesItsClientInterface $entity): bool
    {
        $holder = $entity->clientHolder();

        return self::clientProperty($holder)->isInitialized($holder);
    }

    private static function clientProperty(object $holder): ReflectionProperty
    {
        return new ReflectionProperty($holder::class, 'client');
    }
}
