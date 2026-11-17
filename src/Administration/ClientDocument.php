<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Oidc\Domain\Client\InventoriedClient;

/**
 * A client of the tenant as the administration API writes it, in the list and on its own: its identifier and its
 * name, the source it came from, and its dates.
 */
final readonly class ClientDocument
{
    /**
     * @return array{client_id: string, client_name: string|null, source: string, registered_at: string|null, last_used_at: string|null}
     */
    public static function of(InventoriedClient $client): array
    {
        return [
            'client_id' => $client->clientId,
            'client_name' => $client->clientName,
            'source' => $client->source,
            'registered_at' => AdministrationAnswer::moment($client->registeredAt),
            'last_used_at' => AdministrationAnswer::moment($client->lastUsedAt),
        ];
    }
}
