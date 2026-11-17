<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\PersonDocument;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredDisabling;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Exception\OwnAccountRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Disables a person of the tenant, what `beffroi:user:disable` does (decision 0038): they can no longer sign in, every
 * session of theirs ends, its clients told by back-channel logout, and the act names the administrator. A person
 * disabled already is answered as they are, and an administrator does not disable the account they act with. The
 * answer writes the account and its claims as they now are.
 */
#[AsController]
final readonly class UserDisablingController
{
    public function __construct(
        private AdministeredDisabling $disabling,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/users/{id}/disabled',
        name: 'beffroi_administration_user_disabling',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['PUT'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        try {
            $user = Uuid::isValid($id) ? $this->disabling->disable($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        } catch (OwnAccountRefusedException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }
        if ($user === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no person of this identifier.');
        }

        return AdministrationAnswer::of(PersonDocument::account($user));
    }
}
