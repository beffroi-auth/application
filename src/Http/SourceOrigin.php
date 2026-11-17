<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Encoding\Host;
use LogicException;
use function preg_match;

/**
 * An origin a page names in its Content-Security-Policy (CSP Level 3 §2.3.1), checked because it is written into the
 * header as it is: a ";", a "," or whitespace would start a directive, a policy or a source of its own.
 */
final readonly class SourceOrigin
{
    private const string ORIGIN = '~^[A-Za-z][A-Za-z0-9+.-]*://' . Host::PATTERN . '(?::[0-9]{1,5})?\z~';

    /**
     * @throws LogicException
     */
    public static function of(string $origin): string
    {
        if (preg_match(self::ORIGIN, $origin) !== 1) {
            throw new LogicException('A page names an origin in its Content-Security-Policy as a scheme, a host and a port, and nothing else, which would be written into the header as it is (CSP Level 3 §2.3.1, beffroi/saml S-36).');
        }

        return $origin;
    }
}
