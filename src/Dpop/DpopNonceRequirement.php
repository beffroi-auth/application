<?php

declare(strict_types=1);

namespace App\Dpop;

use Beffroi\Oidc\Port\DpopNonceInterface;
use Closure;

/**
 * Chosen when the container builds the port: at compile time an environment variable has no value yet.
 */
final readonly class DpopNonceRequirement
{
    /**
     * @param Closure(): DpopNonceInterface $asked
     * @param Closure(): DpopNonceInterface $notAsked
     */
    public static function chosen(bool $required, Closure $asked, Closure $notAsked): DpopNonceInterface
    {
        return $required ? $asked() : $notAsked();
    }
}
