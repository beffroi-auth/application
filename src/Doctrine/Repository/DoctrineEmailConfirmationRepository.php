<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Confirmation\EmailConfirmation;

/**
 * @extends DoctrineAddressConfirmationRepository<EmailConfirmation>
 */
final readonly class DoctrineEmailConfirmationRepository extends DoctrineAddressConfirmationRepository
{
    public function __construct(EntityManagers $managers, BoundedDelete $sweep)
    {
        parent::__construct($managers, $sweep, EmailConfirmation::class);
    }
}
