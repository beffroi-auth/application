<?php

declare(strict_types=1);

namespace App\Controller;

use App\Confirmation\WiredAddressConfirmations;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page the link of a confirmation opens, which establishes nothing and verifies nothing: a scanner that fetches
 * the links of a message confirms no address, and the person it was sent for confirms by the form it holds.
 */
#[AsController]
final readonly class AccountAddressLinkController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_address_link';

    public function __construct(
        private AccountAccess $account,
        private WiredAddressConfirmations $confirmations,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/addresses/{claim}/{confirmation}/{value}',
        name: self::ROUTE,
        methods: ['GET'],
        requirements: [
            'claim' => '[a-z_]+',
            'confirmation' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ],
    )]
    public function __invoke(Request $request, string $claim, string $confirmation, string $value): Response
    {
        $session = $this->account->granted($request);
        if ($session instanceof Response) {
            return $session;
        }
        if ($this->confirmations->named($claim) === null) {
            throw new NotFoundHttpException('No such address.');
        }

        return $this->pages->render('account/address_link.html.twig', [
            'claim' => $claim,
            'confirmation' => $confirmation,
            'value' => $value,
        ]);
    }
}
