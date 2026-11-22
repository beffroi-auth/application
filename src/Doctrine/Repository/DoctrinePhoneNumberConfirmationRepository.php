<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Confirmation\PhoneNumberConfirmation;

/**
 * @extends DoctrineAddressConfirmationRepository<PhoneNumberConfirmation>
 */
final readonly class DoctrinePhoneNumberConfirmationRepository extends DoctrineAddressConfirmationRepository
{
    public function __construct(EntityManagers $managers, BoundedDelete $sweep)
    {
        parent::__construct($managers, $sweep, PhoneNumberConfirmation::class);
    }
}
