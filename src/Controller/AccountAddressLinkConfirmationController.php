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
use Symfony\Component\Uid\Uuid;

/**
 * NIST SP 800-63B-4 §4.2.1.2: the person the link was sent for gives it back by a POST, which establishes the address
 * it was sent to; a refusal answers 404 and one flat sentence (decision 0006).
 */
#[AsController]
final readonly class AccountAddressLinkConfirmationController implements ReachedOnTheSessionAloneInterface
{
    public const string ROUTE = 'beffroi_interaction_account_address_link_confirm';

    public const string CSRF_TOKEN_ID = 'beffroi_address_link';

    public function __construct(
        private AccountAccess $account,
        private WiredAddressConfirmations $confirmations,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/account/addresses/{claim}/{confirmation}/{value}',
        name: self::ROUTE,
        methods: ['POST'],
        requirements: [
            'claim' => '[a-z_]+',
            'confirmation' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ],
    )]
    public function __invoke(Request $request, string $claim, string $confirmation, string $value): Response
    {
        $session = $this->account->granted($request, self::CSRF_TOKEN_ID);
        if ($session instanceof Response) {
            return $session;
        }
        $establishment = $this->confirmations->named($claim) ?? throw new NotFoundHttpException('No such address.');
        try {
            $establishment->confirmByLink($session->user, Uuid::fromString($confirmation), $value, $this->contexts->fromRequest($request));
        } catch (AddressConfirmationRefusedException) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, AccountAddressCodeController::REFUSED);
        }

        return $this->pages->afterTheForm(AccountProfileController::ROUTE);
    }
}
