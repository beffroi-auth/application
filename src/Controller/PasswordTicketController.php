<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InteractionPages;
use App\Http\PasswordPage;
use App\Http\PresentedTicket;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Ticket\PasswordTicketRedemption;
use Beffroi\Core\Domain\Credential\PasswordLength;
use Beffroi\Core\Domain\Credential\PlainPassword;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page of a password ticket (decision 0026): the form where the person it names poses a new password, without
 * the one in use and without a session. A ticket that opens nothing answers 404 and one flat sentence.
 */
#[AsController]
final readonly class PasswordTicketController
{
    public const string ROUTE = 'beffroi_interaction_password_ticket';

    public const string REFUSED = 'password_ticket_refused';

    public function __construct(
        private PresentedTicket $tickets,
        private PasswordTicketRedemption $redemption,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/password/{ticket}/{value}',
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

        return $this->pages->render('password_ticket.html.twig', [
            'user' => $ticket->user,
            'ticket' => $this->tickets->parameters($request),
            'refusal' => $this->refusals->take($request)?->code,
            'minimum' => PasswordLength::under($ticket->tenant->loginFloor)->minimum,
            'floor' => PlainPassword::MINIMUM_STRENGTH,
            'rungs' => PasswordPage::RUNG_KEYS,
        ]);
    }
}
