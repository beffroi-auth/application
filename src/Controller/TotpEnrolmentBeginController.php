<?php

declare(strict_types=1);

namespace App\Controller;

use Beffroi\Core\Application\Identity\FederatedDomains;
use App\Http\Account\AsksForARecentProofInterface;
use App\Http\AccountAccess;
use App\Http\RequestContextFactory;
use App\Http\TotpProvisioningStore;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Totp\TotpEnrolment;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class TotpEnrolmentBeginController implements AsksForARecentProofInterface
{
    public const string ROUTE = 'beffroi_interaction_account_two_factor_begin';

    public const string CSRF_TOKEN_ID = 'beffroi_two_factor_begin';

    public const string ALREADY_ENROLLED = 'already_enrolled';

    public const string FEDERATED_DOMAIN = 'federated_domain';

    public function __construct(
        private AccountAccess $account,
        private RequestContextFactory $contexts,
        private TotpEnrolment $enrolment,
        private TotpProvisioningStore $provisionings,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
        private FederatedDomains $federated,
    ) {
    }

    public function comesBackTo(): string
    {
        return AccountTwoFactorController::ROUTE;
    }

    #[Route('/account/two-factor/begin', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $user = $session->user;
        // The core refuses it too; refused here, before a secret is drawn, so that the page can say why.
        if (! $this->federated->admitsLocalCredentials($user)) {
            $this->refusals->keep($request, self::FEDERATED_DOMAIN);

            return $this->pages->afterTheForm(AccountTwoFactorController::ROUTE);
        }
        try {
            $provisioning = $this->enrolment->begin($user);
        } catch (CredentialEnrolmentFailedException) {
            $this->refusals->keep($request, self::ALREADY_ENROLLED);

            return $this->pages->afterTheForm(AccountTwoFactorController::ROUTE);
        }
        $this->provisionings->store($request->getSession(), $user, $provisioning, $this->contexts->fromRequest($request)->receivedAt);

        return $this->pages->afterTheForm(AccountTwoFactorEnrolmentController::ROUTE);
    }
}
