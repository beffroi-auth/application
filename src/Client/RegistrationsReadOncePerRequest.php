<?php

declare(strict_types=1);

namespace App\Client;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Client\ClientRegistration;
use Beffroi\Oidc\Port\ClientSourceInterface;
use function iterator_to_array;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The registrations of a store, read from it once per request and forgotten by the kernel before the next one: the
 * discovery document asks for them five times, and the store rebuilds each of them from its row every time.
 */
final class RegistrationsReadOncePerRequest implements ClientSourceInterface, ResetInterface
{
    /**
     * @var array<string, list<ClientRegistration>> by tenant
     */
    private array $registrations = [];

    public function __construct(
        private readonly ClientSourceInterface $source,
    ) {
    }

    public function findByClientId(Tenant $tenant, string $clientId): ?Client
    {
        return $this->source->findByClientId($tenant, $clientId);
    }

    /**
     * @return list<ClientRegistration>
     */
    public function registrationsIn(Tenant $tenant): array
    {
        return $this->registrations[$tenant->id->toRfc4122()] ??= iterator_to_array($this->source->registrationsIn($tenant), false);
    }

    public function reset(): void
    {
        $this->registrations = [];
    }
}
