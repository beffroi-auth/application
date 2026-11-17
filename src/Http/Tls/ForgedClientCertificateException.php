<?php

declare(strict_types=1);

namespace App\Http\Tls;

use RuntimeException;
use function sprintf;

/**
 * A client certificate carried in a header, which only the sender can set: refused, never ignored (decision 0006).
 */
final class ForgedClientCertificateException extends RuntimeException
{
    public static function carriedBy(string $header): self
    {
        return new self(sprintf('The request carries a client certificate in the "%s" header, which nothing in front of this application sets.', $header));
    }
}
