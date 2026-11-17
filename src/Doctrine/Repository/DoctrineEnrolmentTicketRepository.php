<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Enrolment\EnrolmentTicket;
use Beffroi\Core\Port\EnrolmentTicketRepositoryInterface;

/**
 * @extends DoctrineTicketRepository<EnrolmentTicket>
 */
final readonly class DoctrineEnrolmentTicketRepository extends DoctrineTicketRepository implements EnrolmentTicketRepositoryInterface
{
    public function __construct(EntityManagers $managers, BoundedDelete $sweep)
    {
        parent::__construct($managers, $sweep, EnrolmentTicket::class);
    }
}
