<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Leaves the decision to the retry policy of the lane: Messenger retries a recoverable exception whatever
 * that policy says, unless forceRetry is false.
 */
final class NotificationNotDeliveredException extends RecoverableMessageHandlingException
{
    public function __construct(string $detail)
    {
        parent::__construct($detail, forceRetry: false);
    }
}
