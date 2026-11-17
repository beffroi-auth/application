<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Session\AuthenticationProofs;

/**
 * @extends JsonbObjectType<AuthenticationProofs>
 */
final class AuthenticationProofsType extends JsonbObjectType
{
    public const string NAME = 'beffroi_authentication_proofs';

    protected function encode(object $value): array
    {
        return $value->toTimestamps();
    }

    protected function decode(array $data): object
    {
        return AuthenticationProofs::fromTimestamps($data);
    }

    protected function expects(): string
    {
        return AuthenticationProofs::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
