<?php

declare(strict_types=1);

namespace App\Command;

use App\Http\TicketLink;
use Beffroi\Core\Domain\Ticket\IssuedTicket;
use Beffroi\Core\Domain\Ticket\Ticket;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What a command prints of a ticket it issued, once: the person, the instant it stops opening, and its link.
 */
final readonly class IssuedTicketOutput
{
    public function __construct(
        private TicketLink $links,
    ) {
    }

    /**
     * @param string               $route  the route of the page that opens a ticket of that kind
     * @param IssuedTicket<Ticket> $issued
     */
    public function print(SymfonyStyle $io, string $route, IssuedTicket $issued): void
    {
        $io->definitionList(
            [
                'Person' => $issued->ticket->user->username,
            ],
            [
                'Opens until' => $issued->ticket->expiresAt->format('c'),
            ],
            [
                'Link' => $this->links->of($route, $issued),
            ],
        );
    }
}
