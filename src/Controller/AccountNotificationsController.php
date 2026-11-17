<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\WhatWasRefused;
use App\Http\NotificationsPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountNotificationsController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_notifications';

    public function __construct(
        private AccountAccess $account,
        private WhatWasRefused $refusals,
        private NotificationsPage $page,
    ) {
    }

    #[Route('/account/notifications', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }

        return $this->page->render($session->user, $this->refusals->take($request)?->code);
    }
}
