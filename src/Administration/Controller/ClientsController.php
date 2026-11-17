<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\ClientDocument;
use Beffroi\Oidc\Application\Client\ClientInventory;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The clients of the tenant, where each one came from, when it was registered and when it was last used: what
 * `beffroi:client:list` reads, from the same inventory (decision 0038).
 */
#[AsController]
final readonly class ClientsController
{
    public function __construct(
        private ClientInventory $clients,
    ) {
    }

    #[Route('/clients', name: 'beffroi_administration_clients', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $clients = [];
        foreach ($this->clients->inventoryIn($administrator->tenant) as $client) {
            $clients[] = ClientDocument::of($client);
        }

        return AdministrationAnswer::of(['clients' => $clients]);
    }
}
