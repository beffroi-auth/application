<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredConsents;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Takes back a consent in force of a person of the tenant, what `beffroi:user:revoke-consent` does (decision 0038), by
 * the identifier the read of their consents answers: what was issued under it is revoked, an access token verified
 * offline living to its expiry, and the next authorization request of the application asks again. The act names the
 * administrator.
 */
#[AsController]
final readonly class UserConsentRevocationController
{
    public function __construct(
        private AdministeredConsents $consents,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/users/{id}/consents/{consent}',
        name: 'beffroi_administration_user_consent_revocation',
        requirements: ['id' => '[0-9a-fA-F-]{36}', 'consent' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id, string $consent): Response
    {
        $revoked = Uuid::isValid($id) && Uuid::isValid($consent)
            ? $this->consents->revoke($administrator, Uuid::fromString($id), Uuid::fromString($consent), $this->contexts->fromRequest($request))
            : null;
        if ($revoked === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The person holds no consent in force of this identifier.');
        }

        return AdministrationAnswer::of([
            'id' => $revoked->id->toRfc4122(),
            'client_id' => $revoked->clientId,
            'revoked_at' => AdministrationAnswer::moment($revoked->revokedAt),
        ]);
    }
}
