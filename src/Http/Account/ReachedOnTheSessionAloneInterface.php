<?php

declare(strict_types=1);

namespace App\Http\Account;

/**
 * Ending a session asks no recent proof: that would ask for a password on the very browser a person fears.
 */
interface ReachedOnTheSessionAloneInterface
{
}
