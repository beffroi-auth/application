<?php

declare(strict_types=1);

namespace App\Controller;

use App\Confirmation\WiredAddressConfirmations;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Confirmation\AddressConfirmationIssuing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page where the person types the code sent to an address of theirs; an address that awaits no confirmation sends
 * them back to their profile.
 */
#[AsController]
final readonly class AccountAddressConfirmationController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_address_confirmation';

    public function __construct(
        private AccountAccess $account,
        private WiredAddressConfirmations $confirmations,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/addresses/{claim}/confirmation',
        name: self::ROUTE,
        methods: ['GET'],
        requirements: [
            'claim' => '[a-z_]+',
        ],
    )]
    public function __invoke(Request $request, string $claim): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        $establishment = $this->confirmations->named($claim) ?? throw new NotFoundHttpException('No such address.');
        $address = $establishment->awaits($session->user);
        if ($address === null) {
            return $this->pages->redirectToRoute(AccountProfileController::ROUTE);
        }

        return $this->pages->render('account/address_confirmation.html.twig', [
            'claim' => $claim,
            'address' => $address,
            'digits' => AddressConfirmationIssuing::DIGITS,
            'refusal' => $this->refusals->take($request)?->code,
        ]);
    }
}
