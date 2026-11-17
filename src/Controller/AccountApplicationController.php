<?php

declare(strict_types=1);

namespace App\Controller;

use function array_find;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\ApplicationPage;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Oidc\Port\ActiveConsentsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The application is found among the user's own consents: somebody else's has no page.
 */
#[AsController]
final readonly class AccountApplicationController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_application';

    public function __construct(
        private AccountAccess $account,
        private ActiveConsentsInterface $consents,
        private ApplicationPage $page,
    ) {
    }

    #[Route(
        '/account/applications/{id}',
        name: self::ROUTE,
        methods: ['GET'],
        requirements: [
            'id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ],
    )]
    public function __invoke(Request $request, string $id): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $consent = array_find(
            $this->consents->activeOf($session->user),
            static fn (Consent $consent): bool => $consent->id->toRfc4122() === $id,
        );
        if ($consent === null) {
            throw new NotFoundHttpException('No such application.');
        }

        return $this->page->render($session->user, $consent);
    }
}
