<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Ticket\TotpTicket;
use Beffroi\Core\Port\TotpTicketRepositoryInterface;

/**
 * @extends DoctrineTicketRepository<TotpTicket>
 */
final readonly class DoctrineTotpTicketRepository extends DoctrineTicketRepository implements TotpTicketRepositoryInterface
{
    public function __construct(EntityManagers $managers, BoundedDelete $sweep)
    {
        parent::__construct($managers, $sweep, TotpTicket::class);
    }
}
