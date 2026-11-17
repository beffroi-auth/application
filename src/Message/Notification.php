<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Something carried to a third party, on a transport of its own. Never a Command nor a SyncMessage as well: a message
 * goes to every transport its types are routed to.
 */
interface Notification
{
}
