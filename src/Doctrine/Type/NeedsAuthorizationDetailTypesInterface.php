<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Port\AuthorizationDetailsReaderInterface;

/**
 * The wiring goes to the class: Doctrine builds its types itself, from a class name, at the first connection.
 */
interface NeedsAuthorizationDetailTypesInterface
{
    public static function resolveWith(AuthorizationDetailsReaderInterface $types): void;
}
