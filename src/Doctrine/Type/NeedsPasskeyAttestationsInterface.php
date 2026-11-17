<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Application\Passkey\PasskeyAttestations;

/**
 * The wiring goes to the class: Doctrine builds its types itself, from a class name, at the first connection.
 */
interface NeedsPasskeyAttestationsInterface
{
    public static function resolveWith(PasskeyAttestations $attestations): void;
}
