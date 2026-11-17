<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\AskedDocument;
use App\Administration\GroupDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Exception\GroupAlreadyExistsException;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Application\Administration\AdministeredGroupCreation;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creates a group the tenant holds itself, what `beffroi:group:create` does (decision 0038), from a JSON object naming
 * it exactly as it is to be held. The creation is an act of the tenant that names the administrator; a group the
 * environment names administers the tenant as soon as it has members.
 */
#[AsController]
final readonly class GroupCreationController
{
    private const string ASKED = 'A group is created from a JSON object naming it, "name".';

    public function __construct(
        private AdministeredGroupCreation $groups,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/groups', name: 'beffroi_administration_group_creation', methods: ['POST'])]
    public function __invoke(ActingAdministrator $administrator, Request $request): Response
    {
        try {
            $name = AskedDocument::of($request, 'group asked for')?->nullableString('name');
            if ($name === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            $group = $this->groups->create($administrator, $name, $this->contexts->fromRequest($request));
        } catch (InvalidArgumentException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $exception->getMessage());
        } catch (GroupAlreadyExistsException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }

        return AdministrationAnswer::of(GroupDocument::of($group), Response::HTTP_CREATED);
    }
}
