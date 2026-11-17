<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\GroupDocument;
use function array_map;
use Beffroi\Oidc\Application\Administration\AdministeredGroups;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The groups of the tenant by name, with how many members each holds and whether they administer the tenant (decision
 * 0038). It delivers no person, so it writes no act.
 */
#[AsController]
final readonly class GroupsController
{
    public function __construct(
        private AdministeredGroups $groups,
    ) {
    }

    #[Route('/groups', name: 'beffroi_administration_groups', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        return AdministrationAnswer::of([
            'groups' => array_map(GroupDocument::of(...), $this->groups->all($administrator)),
        ]);
    }
}
