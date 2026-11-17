<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\ApplicationHolding;
use App\Account\ApplicationHoldings;
use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use function array_map;
use Beffroi\Oidc\Application\Administration\AdministeredConsents;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use function count;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The applications a person of the tenant agreed to, the last consent given to each: what it was granted, how many
 * refresh tokens it can still renew with, when it last obtained one and how many of its requests wait for the person,
 * what `beffroi:user:applications` prints (decision 0038). Reading them is an act of the person that names the
 * administrator (`AdministeredConsents`).
 */
#[AsController]
final readonly class UserConsentsController
{
    public function __construct(
        private AdministeredConsents $consents,
        private ApplicationHoldings $holdings,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/users/{id}/consents', name: 'beffroi_administration_user_consents', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $read = Uuid::isValid($id) ? $this->consents->of($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        if ($read === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no person of this identifier.');
        }

        return AdministrationAnswer::of([
            'consents' => array_map(
                static fn (ApplicationHolding $holding): array => [
                    'id' => $holding->consent->id->toRfc4122(),
                    'client_id' => $holding->consent->clientId,
                    'client_name' => $holding->consent->client->registration->presentation->name,
                    'scope' => (string) $holding->consent->scopes,
                    'claims' => $holding->consent->claims->all,
                    'granted_at' => AdministrationAnswer::moment($holding->consent->grantedAt),
                    'renewable' => $holding->renewable,
                    'last_renewed_at' => AdministrationAnswer::moment($holding->lastIssued),
                    'awaiting' => count($holding->pending),
                ],
                $this->holdings->ofEach($read->user, $read->consents),
            ),
        ]);
    }
}
