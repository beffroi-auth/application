<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\AskedDocument;
use App\Administration\GroupDocument;
use App\Http\RequestContextFactory;
use Beffroi\Core\Exception\GroupMembershipException;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Application\Administration\AdministeredMemberships;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Makes a person of the tenant, named by their username, a member of a group the tenant holds itself, what
 * `beffroi:group:add-member` does (decision 0038): a group whose members administer the tenant makes them an
 * administrator. The membership is an act of the person that names the administrator; a group a directory fills takes
 * no member from here.
 */
#[AsController]
final readonly class GroupMemberAdditionController
{
    private const string ASKED = 'A member is added from a JSON object naming their "username".';

    public function __construct(
        private AdministeredMemberships $memberships,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/groups/{id}/members', name: 'beffroi_administration_group_member_addition', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        try {
            $username = AskedDocument::of($request, 'member asked for')?->nullableString('username');
            if ($username === null) {
                return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', self::ASKED);
            }
            $membership = Uuid::isValid($id)
                ? $this->memberships->add($administrator, Uuid::fromString($id), $username, $this->contexts->fromRequest($request))
                : null;
        } catch (InvalidArgumentException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', $exception->getMessage());
        } catch (GroupMembershipException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }
        if ($membership === null) {
            return AdministrationAnswer::refusal(
                Response::HTTP_NOT_FOUND,
                'not_found',
                'The tenant has no group of this identifier, or no person of this username.',
            );
        }

        return AdministrationAnswer::of(GroupDocument::member($membership), Response::HTTP_CREATED);
    }
}
