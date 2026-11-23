<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Administration\GroupDocument;
use App\Http\RequestContextFactory;
use function array_map;
use function is_string;
use Beffroi\Core\Domain\Listing\Pagination;
use Beffroi\Oidc\Application\Administration\AdministeredGroups;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use function preg_match;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The members of a group of the tenant, a page at a time in the order they were added, each with the source of their
 * membership, and how many the group holds. Each member delivered is an act of theirs that names the administrator
 * (decisions 0022 and 0038), and a person of the budget of the administrator (decision 0059).
 */
#[AsController]
final readonly class GroupMembersController
{
    private const string PAGE = 'page';

    private const string PER_PAGE = 'per_page';

    public function __construct(
        private AdministeredGroups $groups,
        private RequestContextFactory $contexts,
    ) {
    }

    #[Route('/groups/{id}/members', name: 'beffroi_administration_group_members', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        $query = $request->query->all();
        $number = self::number($query[self::PAGE] ?? null, 1, null);
        $size = self::number($query[self::PER_PAGE] ?? null, AdministeredGroups::PAGE, AdministeredGroups::LARGEST_PAGE);
        if ($number === null || $size === null) {
            return AdministrationAnswer::refusal(Response::HTTP_BAD_REQUEST, 'invalid_request', sprintf(
                'A page of members is asked for by "%s", a number from 1, and "%s", a number from 1 to %d.',
                self::PAGE,
                self::PER_PAGE,
                AdministeredGroups::LARGEST_PAGE,
            ));
        }
        $members = Uuid::isValid($id)
            ? $this->groups->members($administrator, Uuid::fromString($id), new Pagination($number, $size), $this->contexts->fromRequest($request))
            : null;
        if ($members === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no group of this identifier.');
        }

        return AdministrationAnswer::of([
            'members' => array_map(GroupDocument::member(...), $members->rows),
            'page' => $members->pagination->number,
            'per_page' => $members->pagination->size,
            'pages' => $members->pages(),
            'total' => $members->total,
        ]);
    }

    /**
     * A number of the query written in decimal digits alone, the default when the query does not name it, and null
     * for anything else or past the largest.
     */
    private static function number(mixed $asked, int $default, ?int $largest): ?int
    {
        if ($asked === null) {
            return $default;
        }
        if (! is_string($asked) || preg_match('/^[1-9][0-9]{0,8}$/D', $asked) !== 1) {
            return null;
        }
        $number = (int) $asked;

        return $largest !== null && $number > $largest ? null : $number;
    }
}
