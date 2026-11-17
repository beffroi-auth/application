<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\ClientDocument;
use Beffroi\Oidc\Application\Client\ClientInventory;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Port\ClientStoreInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * One client of the tenant: what `beffroi:client:show` reads (decision 0038). A client registered here comes with what
 * it registered (RFC 7591 §2), what the registration policy of the tenant assigned it, and whether it is a client of
 * this API; a client the deployment declares comes with none of them, being what its configuration says.
 */
#[AsController]
final readonly class ClientController
{
    public function __construct(
        private ClientInventory $clients,
        private ClientStoreInterface $store,
    ) {
    }

    #[Route('/clients/{clientId}', name: 'beffroi_administration_client', requirements: ['clientId' => '[^/]+'], methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, string $clientId): Response
    {
        $client = $this->clients->find($administrator->tenant, $clientId);
        if ($client === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no client of this identifier.');
        }
        $record = $this->store->findRecord($administrator->tenant, $client->clientId);

        return AdministrationAnswer::of(
            ClientDocument::of($client)
            + [
                'administration' => $record?->administration,
                'metadata' => $record === null ? null : (object) $record->metadata,
                'assigned' => $record === null ? null : (object) $record->assigned,
            ],
        );
    }
}
