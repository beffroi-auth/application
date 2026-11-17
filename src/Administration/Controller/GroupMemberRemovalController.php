<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Http\RequestContextFactory;
use Beffroi\Core\Exception\GroupMembershipException;
use Beffroi\Oidc\Application\Administration\AdministeredMemberships;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Beffroi\Oidc\Exception\OwnAccountRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Takes a person out of a group the tenant holds itself, what `beffroi:group:remove-member` does (decision 0038): out of
 * the last group whose members administer the tenant, they no longer administer it. The change is an act of the person
 * that names the administrator. A membership a directory gave is taken back by that directory alone, and an
 * administrator does not take themselves out of a group that makes them one.
 */
#[AsController]
final readonly class GroupMemberRemovalController
{
    public function __construct(
        private AdministeredMemberships $memberships,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route(
        '/groups/{id}/members/{person}',
        name: 'beffroi_administration_group_member_removal',
        requirements: ['id' => '[0-9a-fA-F-]{36}', 'person' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id, string $person): Response
    {
        try {
            $group = Uuid::isValid($id) && Uuid::isValid($person)
                ? $this->memberships->remove($administrator, Uuid::fromString($id), Uuid::fromString($person), $this->contexts->fromRequest($request))
                : null;
        } catch (GroupMembershipException|OwnAccountRefusedException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }
        if ($group === null) {
            return AdministrationAnswer::refusal(
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The tenant has no group of this identifier, or the person is not one of its members.',
            );
        }

        return AdministrationAnswer::of(['group' => $group->id->toRfc4122(), 'person' => $person]);
    }
}
