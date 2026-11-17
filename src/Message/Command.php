<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A write whose result the request does not need. Never a SyncMessage as well: a message goes to every transport its
 * types are routed to.
 */
interface Command
{
}
