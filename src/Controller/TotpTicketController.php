<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InteractionPages;
use App\Http\PresentedTicket;
use App\Http\RequestContextFactory;
use App\Http\TotpProvisioningStore;
use App\Http\TotpSecretShown;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Ticket\TotpTicketRedemption;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page of a one-time password ticket (decision 0026): the secret of a new authenticator app for the person it
 * names, and the form of its first code, without a session. The secret is drawn the first time the page is read and
 * kept sealed in the session of the browser, as the account keeps it (TotpProvisioningStore). A ticket that opens
 * nothing answers 404 and one flat sentence.
 */
#[AsController]
final readonly class TotpTicketController
{
    public const string ROUTE = 'beffroi_interaction_totp_ticket';

    public const string REFUSED = 'totp_ticket_refused';

    /**
     * The floor of the tenant or the domain of the person admits no authenticator app, which a ticket issued before
     * either changed may still name.
     */
    public const string NOT_ADMITTED = 'not_admitted';

    public function __construct(
        private PresentedTicket $tickets,
        private TotpTicketRedemption $redemption,
        private TotpProvisioningStore $provisionings,
        private TotpSecretShown $secrets,
        private RequestContextFactory $contexts,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/two-factor/{ticket}/{value}',
        name: self::ROUTE,
        requirements: [
            'ticket' => '[0-9a-fA-F-]{36}',
        ],
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
    {
        $ticket = $this->tickets->of($request, $this->redemption->redeem(...));
        if ($ticket === null) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, self::REFUSED);
        }
        $page = [
            'user' => $ticket->user,
            'ticket' => $this->tickets->parameters($request),
            'refusal' => $this->refusals->take($request)?->code,
            'qr_code' => null,
            'key' => null,
        ];
        $now = $this->contexts->fromRequest($request)->receivedAt;
        $provisioning = $this->provisionings->provisioning($request->getSession(), $ticket->user, $now);
        if ($provisioning === null) {
            try {
                $provisioning = $this->redemption->begin($ticket);
            } catch (CredentialEnrolmentFailedException) {
                return $this->pages->render('totp_ticket.html.twig', [...$page, 'refusal' => self::NOT_ADMITTED]);
            }
            $this->provisionings->store($request->getSession(), $ticket->user, $provisioning, $now);
        }

        return $this->pages->render('totp_ticket.html.twig', [...$page, ...$this->secrets->of($provisioning)]);
    }
}
