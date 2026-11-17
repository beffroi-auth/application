<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\PersonDocument;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Administration\AdministeredEnabling;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Enables a person of the tenant again, what `beffroi:user:enable` does (decision 0038): they may sign in again, and the
 * act names the administrator. A person enabled already is answered as they are. The answer writes the account and its
 * claims as they now are.
 */
#[AsController]
final readonly class UserEnablingController
{
    public function __construct(
        private AdministeredEnabling $enabling,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/users/{id}/disabled',
        name: 'beffroi_administration_user_enabling',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $user = Uuid::isValid($id) ? $this->enabling->enable($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        if ($user === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no person of this identifier.');
        }

        return AdministrationAnswer::of(PersonDocument::account($user));
    }
}
