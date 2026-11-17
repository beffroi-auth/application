<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\WhatWasRefused;
use App\Http\PasskeysPage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountPasskeysController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_passkeys';

    public function __construct(
        private AccountAccess $account,
        private WhatWasRefused $refusals,
        private PasskeysPage $page,
    ) {
    }

    public function comesBackTo(): string
    {
        return self::ROUTE;
    }

    #[Route('/account/passkeys', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }

        return $this->page->render($session->user, $this->refusals->take($request)?->code);
    }
}
