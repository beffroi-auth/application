<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Push\PushSubscriptionNaming;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
final readonly class PushSubscriptionNameController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_notifications_name';

    public const string CSRF_TOKEN_ID = 'beffroi_push_name';

    public const string LABEL_PARAMETER = 'label';

    public const string INVALID_LABEL = 'invalid_label';

    public function __construct(
        private AccountAccess $account,
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSubscriptionNaming $naming,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/notifications/{id}/name',
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

        try {
            $this->naming->rename($subscription, $request->request->getString(self::LABEL_PARAMETER));
        } catch (InvalidArgumentException) {
            $this->refusals->keep($request, self::INVALID_LABEL);
        }

        return $this->pages->afterTheForm(AccountNotificationsController::ROUTE);
    }
}
