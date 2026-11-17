<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\RequestContextFactory;
use App\Http\TotpProvisioningStore;
use App\Http\InteractionPages;
use App\Http\TwoFactorPage;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Totp\TotpEnrolment;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class TotpEnrolmentController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_two_factor_enrol';

    public const string CSRF_TOKEN_ID = 'beffroi_two_factor_enrol';

    public const string CODE_PARAMETER = 'code';

    public const string NO_PROVISIONING = 'no_provisioning';

    public const string REFUSED = 'refused';

    public function __construct(
        private AccountAccess $account,
        private RequestContextFactory $contexts,
        private TotpEnrolment $enrolment,
        private TotpProvisioningStore $provisionings,
        private WhatWasRefused $refusals,
        private TwoFactorPage $page,
        private InteractionPages $pages,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountTwoFactorEnrolmentController::ROUTE;
    }

    #[Route('/account/two-factor', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        $context = $this->contexts->fromRequest($request);
        $provisioning = $this->provisionings->provisioning($request->getSession(), $user, $context->receivedAt);
        if ($provisioning === null) {
            $this->refusals->keep($request, self::NO_PROVISIONING);

            return $this->pages->afterTheForm(AccountTwoFactorController::ROUTE);
        }
        $code = $request->request->getString(self::CODE_PARAMETER);
        try {
            $this->enrolment->complete($user, $provisioning, $code, $context);
        } catch (CredentialEnrolmentFailedException) {
            $this->refusals->keep($request, self::REFUSED);

            return $this->pages->afterTheForm(AccountTwoFactorEnrolmentController::ROUTE);
        }
        $this->provisionings->forget($request->getSession());

        return $this->page->renderEnrolled($user, $provisioning->backupCodes);
    }
}
