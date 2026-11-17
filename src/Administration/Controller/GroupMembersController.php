<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\GroupDocument;
use App\Http\RequestContextFactory;
use function array_map;
use Beffroi\Oidc\Application\Administration\AdministeredGroups;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The members of a group of the tenant, in the order they were added, each with the source of their membership. Each
 * member delivered is an act of theirs that names the administrator (decisions 0022 and 0038).
 */
#[AsController]
final readonly class GroupMembersController
{
    public function __construct(
        private AdministeredGroups $groups,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/groups/{id}/members', name: 'beffroi_administration_group_members', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $members = Uuid::isValid($id) ? $this->groups->members($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        if ($members === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no group of this identifier.');
        }

        return AdministrationAnswer::of([
            'members' => array_map(GroupDocument::member(...), $members),
        ]);
    }
}
