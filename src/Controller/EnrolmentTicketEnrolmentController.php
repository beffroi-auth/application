<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\FormToken;
use App\Http\InteractionPages;
use App\Http\PasskeyOptionsStore;
use App\Http\PresentedTicket;
use App\Http\RequestContextFactory;
use App\Http\WhatWasRefused;
use Beffroi\Core\Application\Enrolment\EnrolmentTicketRedemption;
use Beffroi\Core\Application\Passkey\PasskeyEnrolment;
use Beffroi\Core\Exception\CredentialEnrolmentFailedException;
use Beffroi\Core\Exception\PasskeyAttestationRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The ticket is spent once the passkey is stored and never before: a failed ceremony leaves the person their link.
 */
#[AsController]
final readonly class EnrolmentTicketEnrolmentController
{
    public const string ROUTE = 'beffroi_interaction_enrolment_ticket_enrol';

    public const string CSRF_TOKEN_ID = 'beffroi_enrolment_ticket';

    public const string ATTESTATION_PARAMETER = '_passkey';

    public const string NAME_PARAMETER = 'name';

    public const string NO_CEREMONY = 'no_ceremony';

    public const string REFUSED = 'refused';

    public function __construct(
        private PresentedTicket $tickets,
        private EnrolmentTicketRedemption $redemption,
        private PasskeyEnrolment $enrolment,
        private PasskeyOptionsStore $options,
        private FormToken $formTokens,
        private WhatWasRefused $refusals,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
    ) {
    }

    #[Route(
        '/enrol/{ticket}/{value}',
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
            return $this->pages->error(Response::HTTP_NOT_FOUND, EnrolmentTicketController::REFUSED);
        }
        $refusal = $this->formTokens->refusal($request->request->getString(FormToken::PARAMETER), self::CSRF_TOKEN_ID);
        if ($refusal !== null) {
            return $refusal;
        }
        $attestation = $request->request->getString(self::ATTESTATION_PARAMETER);
        $options = $this->options->takeCreation($request->getSession(), $ticket->user->id);
        if ($attestation === '' || $options === null) {
            return $this->refused($request, self::NO_CEREMONY);
        }
        try {
            $this->enrolment->complete(
                $ticket->user,
                $request->request->getString(self::NAME_PARAMETER),
                $options,
                $attestation,
                $this->contexts->fromRequest($request),
            );
        } catch (PasskeyAttestationRefusedException $exception) {
            return $this->refused($request, $exception->refusal->value);
        } catch (CredentialEnrolmentFailedException) {
            return $this->refused($request, self::REFUSED);
        }
        $this->redemption->spend($ticket);

        return $this->pages->afterTheForm(AccountController::ROUTE);
    }

    private function refused(Request $request, string $code): Response
    {
        $this->refusals->keep($request, $code);

        return $this->pages->afterTheForm(EnrolmentTicketController::ROUTE, $this->tickets->parameters($request));
    }
}
