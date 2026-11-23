<?php

declare(strict_types=1);

namespace App\Message;

use Beffroi\Core\Domain\Event\DomainEventInterface;
use DateTimeImmutable;

/**
 * A message whose last attempt failing is an act: whoever gives up on it announces what it returns.
 */
interface GivenUpAsAnAct
{
    /**
     * @param string $reason why the last attempt failed
     */
    public function givenUp(DateTimeImmutable $at, string $reason): DomainEventInterface;
}
