<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Ticket\IssuedTicket;
use Beffroi\Core\Domain\Ticket\Ticket;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link of a ticket: the origin of the issuer of its tenant and the path of the page of its kind that opens it,
 * which already carries the path of the issuer. Written by the routes of the provider, which a process of the
 * administration API does not serve.
 */
final readonly class TicketLink
{
    public function __construct(
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @param string               $route  the route of the page that opens a ticket of that kind
     * @param IssuedTicket<Ticket> $issued
     */
    public function of(string $route, IssuedTicket $issued): string
    {
        return (
            $issued->ticket->tenant->issuer->origin()
            . $this->router->generate(
                $route,
                [
                    PresentedTicket::TICKET_PARAMETER => $issued->ticket->id->toRfc4122(),
                    PresentedTicket::VALUE_PARAMETER => $issued->value,
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            )
        );
    }
}
