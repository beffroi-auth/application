<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Authorization\AuthenticationSnapshot;

/**
 * A record that gains one only when answered keeps it as a document: an embeddable cannot be null.
 *
 * @extends JsonbObjectType<AuthenticationSnapshot>
 */
final class AuthenticationSnapshotType extends JsonbObjectType
{
    public const string NAME = 'beffroi_authentication_snapshot';

    protected function encode(object $value): array
    {
        return $value->toClaims();
    }

    protected function decode(array $data): object
    {
        return AuthenticationSnapshot::fromClaims($data);
    }

    protected function expects(): string
    {
        return AuthenticationSnapshot::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
