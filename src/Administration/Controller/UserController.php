<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\PersonDocument;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredPerson;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use function is_string;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A person of the tenant, by their username: the account and the source that created it, the standard claims it holds
 * (OIDC Core §5.1), the credentials with their kind, name, authenticator and dates, and the groups with the source of
 * each membership, what `beffroi:user:show` prints (decision 0038). Reading them is an act of theirs that names the
 * administrator (`AdministeredPerson`).
 */
#[AsController]
final readonly class UserController
{
    public function __construct(
        private AdministeredPerson $persons,
        private RequestContextFactory $contexts,
        private PersonDocument $documents,
    ) {
    }

    #[Route('/users', name: 'beffroi_administration_user', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        $username = $request->query->all()['username'] ?? null;
        if (! is_string($username) || $username === '') {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', 'A person is asked for by the "username" of the query, once.');
        }
        $read = $this->persons->named($administrator, $username, $this->contexts->fromRequest($request));
        if ($read === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'Nobody of the tenant has this username.');
        }
        return AdministrationAnswer::of($this->documents->of($read->user, $read->memberships, $read->credentials));
    }
}
