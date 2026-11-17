<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\InitialAccessTokenDocument;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Client\Registration\InitialAccessTokenRevocation;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Revokes an initial access token of the tenant, what `beffroi:client:token:revoke` does (decision 0038): it authorises
 * no registration from that instant, the clients it registered stay, and the revocation is an act of the tenant that
 * names the administrator.
 */
#[AsController]
final readonly class InitialAccessTokenRevocationController
{
    public function __construct(
        private InitialAccessTokenRevocation $revocation,
        private RequestContextFactory $contexts,
        private ClockInterface $clock,
    ) {
    }

    #[Route(
        '/initial-access-tokens/{id}',
        name: 'beffroi_administration_initial_access_token_revocation',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $token = Uuid::isValid($id)
            ? $this->revocation->revokeByAnAdministrator(
                $administrator->tenant,
                Uuid::fromString($id),
                $administrator->user->subject,
                $this->contexts->fromRequest($request),
            )
            : null;
        if ($token === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no initial access token of this identifier.');
        }

        return AdministrationAnswer::of(InitialAccessTokenDocument::of($token, $this->clock->now()));
    }
}
