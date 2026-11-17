<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredClientRemoval;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Exception\ClientRemovalRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Deletes a client that registered itself, what `beffroi:client:delete` does (decision 0038): nothing it was issued can
 * be spent any more, and the deletion is an act of the tenant that names the administrator. A client the deployment
 * declares, and the one the administrator acts through, are refused.
 */
#[AsController]
final readonly class ClientRemovalController
{
    public function __construct(
        private AdministeredClientRemoval $removal,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/clients/{clientId}', name: 'beffroi_administration_client_removal', requirements: ['clientId' => '[^/]+'], methods: ['DELETE'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $clientId): Response
    {
        $context = $this->contexts->fromRequest($request);
        try {
            $removed = $this->removal->remove($administrator, $clientId, $context);
        } catch (ClientRemovalRefusedException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }
        if (! $removed) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no client of this identifier.');
        }

        return AdministrationAnswer::of([
            'client_id' => $clientId,
            'deleted_at' => AdministrationAnswer::moment($context->receivedAt),
        ]);
    }
}
