<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Oidc\Domain\Client\ResponseFormat;
use function sprintf;

/**
 * How the panel writes the algorithms a client registered for a document: signed, then encrypted.
 */
final readonly class RegisteredShape
{
    public static function of(ResponseFormat $format): string
    {
        $shape = $format->isSigned ? sprintf('signed with %s', $format->signature?->name() ?? 'the algorithm of the provider') : 'unsigned';
        if ($format->encryption !== null) {
            $shape .= sprintf(', encrypted with %s and %s', $format->encryption->algorithm->name(), $format->encryption->method->name());
        }

        return $shape;
    }
}
