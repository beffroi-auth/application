<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\FormToken;
use App\Http\InteractionPages;
use App\Http\PresentedTicket;
use App\Http\RequestContextFactory;
use App\Http\TotpProvisioningStore;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Ticket\TotpTicketRedemption;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The first code of the app a one-time password ticket enrols (decision 0026). The answer to a code that holds is the
 * page of the backup codes, shown this once, and not a redirection: the codes are stored hashed.
 */
#[AsController]
final readonly class TotpTicketEnrolmentController
{
    public const string ROUTE = 'beffroi_interaction_totp_ticket_enrol';

    public const string CSRF_TOKEN_ID = 'beffroi_totp_ticket';

    public function __construct(
        private PresentedTicket $tickets,
        private TotpTicketRedemption $redemption,
        private TotpProvisioningStore $provisionings,
        private FormToken $formTokens,
        private WhatWasRefused $refusals,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/two-factor/{ticket}/{value}',
        name: self::ROUTE,
        requirements: [
            'ticket' => '[0-9a-fA-F-]{36}',
        ],
        methods: ['POST'],
    )]
    public function __invoke(Request $request): Response
    {
        $ticket = $this->tickets->of($request, $this->redemption->redeem(...));
        if ($ticket === null) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, TotpTicketController::REFUSED);
        }
        $refusal = $this->formTokens->refusal($request->request->getString(FormToken::PARAMETER), self::CSRF_TOKEN_ID);
        if ($refusal !== null) {
            return $refusal;
        }
        $context = $this->contexts->fromRequest($request);
        $provisioning = $this->provisionings->provisioning($request->getSession(), $ticket->user, $context->receivedAt);
        if ($provisioning === null) {
            return $this->refused($request, TotpEnrolmentController::NO_PROVISIONING);
        }
        try {
            $this->redemption->spend($ticket, $provisioning, $request->request->getString(TotpEnrolmentController::CODE_PARAMETER), $context);
        } catch (CredentialEnrolmentFailedException) {
            return $this->refused($request, TotpEnrolmentController::REFUSED);
        }
        $this->provisionings->forget($request->getSession());

        return $this->pages->render('totp_ticket_enrolled.html.twig', [
            'backup_codes' => $provisioning->backupCodes,
        ]);
    }

    private function refused(Request $request, string $code): Response
    {
        $this->refusals->keep($request, $code);

        return $this->pages->afterTheForm(TotpTicketController::ROUTE, $this->tickets->parameters($request));
    }
}
