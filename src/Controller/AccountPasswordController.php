<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\PasswordPage;
use App\Http\WhatWasRefused;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountPasswordController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_password';

    public function __construct(
        private AccountAccess $account,
        private WhatWasRefused $refusals,
        private PasswordPage $page,
    ) {
    }

    public function comesBackTo(): string
    {
        return self::ROUTE;
    }

    #[Route('/account/password', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }

        return $this->page->render($request->getSession(), $session->user, $this->refusals->take($request)?->code);
    }
}
