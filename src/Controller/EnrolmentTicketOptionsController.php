<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InteractionPages;
use App\Http\PasskeyOptionsStore;
use App\Http\PresentedTicket;
use Beffroi\Core\Application\Enrolment\EnrolmentTicketRedemption;
use Beffroi\Core\Application\Passkey\PasskeyEnrolment;
use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * WebAuthn Level 3 §7.1
 *
 * The ticket stands in for a session: read at every request, never trusted from the one before.
 */
#[AsController]
final readonly class EnrolmentTicketOptionsController
{
    public const string ROUTE = 'beffroi_interaction_enrolment_ticket_options';

    public function __construct(
        private PresentedTicket $tickets,
        private EnrolmentTicketRedemption $redemption,
        private PasskeyEnrolment $enrolment,
        private WebauthnCeremonies $ceremonies,
        private PasskeyOptionsStore $options,
    ) {
    }

    #[Route(
        '/enrol/{ticket}/{value}/options',
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
            return new JsonResponse(
                [
                    'error' => EnrolmentTicketController::REFUSED,
                ],
                Response::HTTP_NOT_FOUND,
                InteractionPages::HEADERS,
            );
        }
        $options = $this->enrolment->begin($ticket->user);
        $this->options->storeCreation($request->getSession(), $ticket->user->id, $options);

        return new Response($this->ceremonies->optionsToJson($options), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            ...InteractionPages::HEADERS,
        ]);
    }
}
