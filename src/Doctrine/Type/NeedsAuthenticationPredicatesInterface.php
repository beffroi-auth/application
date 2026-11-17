<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Port\AuthenticationPredicatesInterface;

/**
 * The wiring goes to the class: Doctrine builds its types itself, from a class name, at the first connection.
 */
interface NeedsAuthenticationPredicatesInterface
{
    public static function resolveWith(AuthenticationPredicatesInterface $predicates): void;
}
