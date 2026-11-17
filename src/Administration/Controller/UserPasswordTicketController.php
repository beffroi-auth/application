<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Controller\PasswordTicketController;
use App\Http\RequestContextFactory;
use App\Http\TicketLink;
use Beffroi\Core\Exception\TicketRefusedException;
use Beffroi\Oidc\Application\Administration\AdministeredPasswordTicket;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Issues the password ticket of a person of the tenant, what `beffroi:user:password-ticket` does (decisions 0026 and
 * 0038): its link lets whoever holds it pose a new password on the account of the person once, until it expires, and
 * opens no session; spending it ends every session of the person. The password tickets the person had not spent open
 * nothing any more, and the issuing is an act of theirs that names the administrator. The link is answered once.
 */
#[AsController]
final readonly class UserPasswordTicketController
{
    public function __construct(
        private AdministeredPasswordTicket $tickets,
        private RequestContextFactory $contexts,
        private TicketLink $links,
    ) {
    }

    #[Route(
        '/users/{id}/password-ticket',
        name: 'beffroi_administration_user_password_ticket',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    public function __invoke(ActingAdministrator $administrator, Request $request, string $id): Response
    {
        try {
            $issued = Uuid::isValid($id) ? $this->tickets->issue($administrator, Uuid::fromString($id), $this->contexts->fromRequest($request)) : null;
        } catch (TicketRefusedException $exception) {
            return AdministrationAnswer::refusal(Response::HTTP_CONFLICT, 'conflict', $exception->getMessage());
        }
        if ($issued === null) {
            return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The tenant has no person of this identifier.');
        }

        return AdministrationAnswer::of([
            'link' => $this->links->of(PasswordTicketController::ROUTE, $issued),
            'expires_at' => AdministrationAnswer::moment($issued->ticket->expiresAt),
        ], Response::HTTP_CREATED);
    }
}
