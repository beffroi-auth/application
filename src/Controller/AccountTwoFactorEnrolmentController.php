<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\TotpProvisioningStore;
use App\Http\TwoFactorPage;
use App\Http\WhatWasRefused;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AccountTwoFactorEnrolmentController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_two_factor_enrolment';

    public function __construct(
        private AccountAccess $account,
        private TotpProvisioningStore $provisionings,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private TwoFactorPage $page,
        private InteractionPages $pages,
    ) {
    }

    public function comesBackTo(): string
    {
        return self::ROUTE;
    }

    #[Route('/account/two-factor/enrol', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $refusal = $this->refusals->take($request);
        $provisioning = $this->provisionings->provisioning(
            $request->getSession(),
            $session->user,
            $this->contexts->fromRequest($request)->receivedAt,
        );
        if ($provisioning === null) {
            $this->refusals->keep($request, TotpEnrolmentController::NO_PROVISIONING);

            return $this->pages->afterTheForm(AccountTwoFactorController::ROUTE);
        }

        return $this->page->renderEnrolment($session->user, $provisioning, $refusal?->code);
    }
}
