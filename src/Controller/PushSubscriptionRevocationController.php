<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use Beffroi\Core\Application\Push\PushSubscriptionRevocation;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
final readonly class PushSubscriptionRevocationController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_notifications_revoke';

    public const string CSRF_TOKEN_ID = 'beffroi_push_revoke';

    public function __construct(
        private AccountAccess $account,
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSubscriptionRevocation $revocation,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/notifications/{id}/revoke',
        name: self::ROUTE,
        methods: ['POST'],
        requirements: [
            'id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ],
    )]
    public function __invoke(Request $request, string $id): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $subscription = $this->subscriptions->find(Uuid::fromString($id));
        if ($subscription === null || ! $subscription->user->id->equals($session->user->id)) {
            throw new NotFoundHttpException('No such push subscription.');
        }
        $this->revocation->revoke($subscription);

        return $this->pages->afterTheForm(AccountNotificationsController::ROUTE);
    }
}
