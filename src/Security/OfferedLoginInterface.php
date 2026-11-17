<?php

declare(strict_types=1);

namespace App\Security;

interface OfferedLoginInterface extends ProvingLoginInterface
{
    /**
     * The kind of credential this login reads, as Credential::kind() names it.
     */
    public function kind(): string;
}
