<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Ticket\PasswordTicket;
use Beffroi\Core\Port\PasswordTicketRepositoryInterface;

/**
 * @extends DoctrineTicketRepository<PasswordTicket>
 */
final readonly class DoctrinePasswordTicketRepository extends DoctrineTicketRepository implements PasswordTicketRepositoryInterface
{
    public function __construct(EntityManagers $managers, BoundedDelete $sweep)
    {
        parent::__construct($managers, $sweep, PasswordTicket::class);
    }
}
