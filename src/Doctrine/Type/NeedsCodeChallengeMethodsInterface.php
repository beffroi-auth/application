<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Port\CodeChallengeMethodsInterface;

/**
 * The wiring goes to the class: Doctrine builds its types itself, from a class name, at the first connection.
 */
interface NeedsCodeChallengeMethodsInterface
{
    public static function resolveWith(CodeChallengeMethodsInterface $methods): void;
}
