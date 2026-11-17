<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\ProvenanceDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Oidc\Application\Administration\AdministeredSessions;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use function array_map;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The sessions open in the name of a person of the tenant, what each one proved (the "acr" and the "amr", OIDC Core
 * §2, RFC 8176) and where it was last seen from: what `beffroi:user:sessions` prints (decision 0038). Reading them is
 * an act of the person that names the administrator (`AdministeredSessions`).
 */
#[AsController]
final readonly class UserSessionsController
{
    public function __construct(
        private AdministeredSessions $sessions,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/users/{id}/sessions', name: 'beffroi_administration_user_sessions', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $sessions = Uuid::isValid($id)
            ? $this->sessions->of($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request))
            : null;
        if ($sessions === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'Nobody of the tenant has this identifier.');
        }

        return AdministrationAnswer::of([
            'sessions' => array_map(static fn (AuthenticationSession $session): array => [
                'id' => $session->id->toRfc4122(),
                'authenticated_at' => AdministrationAnswer::moment($session->authenticatedAt()),
                'established_at' => AdministrationAnswer::moment($session->establishedAt),
                'last_seen_at' => AdministrationAnswer::moment($session->lastSeenAt),
                'expires_at' => AdministrationAnswer::moment($session->expiresAt),
                'acr' => $session->authenticationContextClass()->value,
                'methods' => $session->proofs->methodReferences(),
                'provenance' => ProvenanceDocument::of($session->lastSeenFrom),
            ], $sessions),
        ]);
    }
}
