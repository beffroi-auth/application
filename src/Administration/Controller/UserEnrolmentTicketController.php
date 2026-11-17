<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Controller\EnrolmentTicketController;
use App\Http\RequestContextFactory;
use App\Http\TicketLink;
use Beffroi\Core\Exception\TicketRefusedException;
use Beffroi\Oidc\Application\Administration\AdministeredEnrolmentTicket;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Issues the enrolment ticket of a person of the tenant, what `beffroi:user:enrolment-ticket` does (decisions 0026 and
 * 0038): its link lets whoever holds it pose a passkey on the account of the person once, until it expires, and opens
 * no session. The enrolment tickets the person had not spent open nothing any more, and the issuing is an act of
 * theirs that names the administrator. The link is answered once: the provider keeps the hash of its value and nothing
 * else.
 */
#[AsController]
final readonly class UserEnrolmentTicketController
{
    public function __construct(
        private AdministeredEnrolmentTicket $tickets,
        private RequestContextFactory $contexts,
        private TicketLink $links,
    ) {
    }

    #[Route(
        '/users/{id}/enrolment-ticket',
        name: 'beffroi_administration_user_enrolment_ticket',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        try {
            $issued = Uuid::isValid($id) ? $this->tickets->issue($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        } catch (TicketRefusedException) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', 'The account of this person is disabled: a ticket would open nothing.');
        }
        if ($issued === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no person of this identifier.');
        }

        return AdministrationAnswer::of([
            'link' => $this->links->of(EnrolmentTicketController::ROUTE, $issued),
            'expires_at' => AdministrationAnswer::moment($issued->ticket->expiresAt),
        ], Response::HTTP_CREATED);
    }
}
