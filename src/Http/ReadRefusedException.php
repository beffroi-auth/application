<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * A bounded read of a third party that was refused, the sentence naming where and why. The caller turns it into the
 * exception its own port declares, the message and the cause carried over.
 */
final class ReadRefusedException extends RuntimeException
{
}
