<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Tenant\SelfServedClaims;

/**
 * @extends JsonObjectType<SelfServedClaims>
 */
final class SelfServedClaimsType extends JsonObjectType
{
    public const string NAME = 'beffroi_self_served_claims';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return SelfServedClaims::fromDocument($data);
    }

    protected function expects(): string
    {
        return SelfServedClaims::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
