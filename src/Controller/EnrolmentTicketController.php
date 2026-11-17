<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InteractionPages;
use App\Http\PresentedTicket;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Enrolment\EnrolmentTicketRedemption;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class EnrolmentTicketController
{
    public const string ROUTE = 'beffroi_interaction_enrolment_ticket';

    public const string REFUSED = 'enrolment_ticket_refused';

    public function __construct(
        private PresentedTicket $tickets,
        private EnrolmentTicketRedemption $redemption,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/enrol/{ticket}/{value}',
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

        return $this->pages->render('enrolment_ticket.html.twig', [
            'user' => $ticket->user,
            'ticket' => $this->tickets->parameters($request),
            'error' => $this->refusals->take($request)?->code,
        ]);
    }
}
