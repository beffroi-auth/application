<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Claims\ClaimNames;

/**
 * @extends JsonObjectType<ClaimNames>
 */
final class ClaimNamesType extends JsonObjectType
{
    public const string NAME = 'beffroi_claim_names';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return ClaimNames::fromDocument($data);
    }

    protected function expects(): string
    {
        return ClaimNames::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
