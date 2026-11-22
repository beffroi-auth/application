<?php

declare(strict_types=1);

namespace App\Controller;

use App\Confirmation\WiredAddressConfirmations;
use App\Http\Account\ReachedOnTheSessionAloneInterface;
use App\Http\AccountAccess;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Exception\AddressConfirmationRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * NIST SP 800-63B-4 §4.2.1.2: a code and a link sent to the address of the person that awaits its confirmation.
 */
#[AsController]
final readonly class AccountAddressConfirmationSendController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_address_confirmation_send';

    public const string CSRF_TOKEN_ID = 'beffroi_address_confirmation_send';

    public function __construct(
        private AccountAccess $account,
        private WiredAddressConfirmations $confirmations,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/addresses/{claim}/confirmation',
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
            $establishment->send($session->user, $this->contexts->fromRequest($request));
        } catch (AddressConfirmationRefusedException) {
            return $this->pages->afterTheForm(AccountProfileController::ROUTE);
        }

        return $this->pages->afterTheForm(AccountAddressConfirmationController::ROUTE, ['claim' => $claim]);
    }
}
