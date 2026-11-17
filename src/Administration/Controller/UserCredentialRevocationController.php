<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredCredentialRevocation;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Revokes a credential of a person of the tenant, what `beffroi:user:revoke-credential` does (decision 0038): it never
 * authenticates again, and stays listed with the moment it was revoked. A password is replaced, never revoked.
 */
#[AsController]
final readonly class UserCredentialRevocationController
{
    public function __construct(
        private AdministeredCredentialRevocation $revocation,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/users/{id}/credentials/{credential}',
        name: 'beffroi_administration_user_credential_revocation',
        requirements: ['id' => '[0-9a-fA-F-]{36}', 'credential' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id, string $credential): Response
    {
        $revoked = Uuid::isValid($id) && Uuid::isValid($credential)
            ? $this->revocation->revoke($administrator, Uuid::fromString($id), Uuid::fromString($credential), $this->contexts->fromRequest($request))
            : null;
        if ($revoked === null) {
            return AdministrationAnswer::refusal(
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The tenant has no person of this identifier, or they have no credential of that one a revocation applies to: a password is replaced, never revoked.',
            );
        }

        return AdministrationAnswer::of([
            'id' => $revoked->id->toRfc4122(),
            'kind' => $revoked->kind(),
            'name' => $revoked->label(),
            'revoked_at' => AdministrationAnswer::moment($revoked->revokedAt),
        ]);
    }
}
