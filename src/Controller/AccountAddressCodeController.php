<?php

declare(strict_types=1);

namespace App\Controller;

use App\Confirmation\WiredAddressConfirmations;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use Beffroi\Core\Exception\AddressConfirmationRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * NIST SP 800-63B-4 §4.2.1.2: the code the person types establishes the address it was sent to; a refusal never says
 * why (decision 0006).
 */
#[AsController]
final readonly class AccountAddressCodeController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_address_confirmation_code';

    public const string CSRF_TOKEN_ID = 'beffroi_address_confirmation_code';

    public const string REFUSED = 'address_confirmation_refused';

    public function __construct(
        private AccountAccess $account,
        private WiredAddressConfirmations $confirmations,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/addresses/{claim}/confirmation/code',
        name: self::ROUTE,
        methods: ['POST'],
        requirements: [
            'claim' => '[a-z_]+',
        ],
    )]
    public function __invoke(Request $request, string $claim): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $establishment = $this->confirmations->named($claim) ?? throw new NotFoundHttpException('No such address.');
        try {
            $establishment->confirm($session->user, $request->request->getString('code'), $this->contexts->fromRequest($request));
        } catch (AddressConfirmationRefusedException) {
            $this->refusals->keep($request, self::REFUSED);

            return $this->pages->afterTheForm(AccountAddressConfirmationController::ROUTE, ['claim' => $claim]);
        }

        return $this->pages->afterTheForm(AccountProfileController::ROUTE);
    }
}
