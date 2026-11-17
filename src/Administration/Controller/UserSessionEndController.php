<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredSessionEnd;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Ends an open session of a person of the tenant, what `beffroi:user:revoke-session` does (decision 0038): the session
 * leaves the open ones, and its relying parties are told by the back-channel (Back-Channel Logout 1.0 §2.5).
 */
#[AsController]
final readonly class UserSessionEndController
{
    public function __construct(
        private AdministeredSessionEnd $sessions,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/users/{id}/sessions/{session}',
        name: 'beffroi_administration_user_session_end',
        requirements: ['id' => '[0-9a-fA-F-]{36}', 'session' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id, string $session): Response
    {
        $ended = Uuid::isValid($id) && Uuid::isValid($session)
            ? $this->sessions->end($administrator, Uuid::fromString($id), Uuid::fromString($session), $this->contexts->fromRequest($request))
            : null;
        if ($ended === null) {
            return AdministrationAnswer::refusal(
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The tenant has no person of this identifier, or they have no open session of that one.',
            );
        }

        return AdministrationAnswer::of([
            'id' => $ended->id->toRfc4122(),
            'ended_at' => AdministrationAnswer::moment($ended->endedAt),
        ]);
    }
}
