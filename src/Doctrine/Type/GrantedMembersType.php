<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Client\Metadata\GrantedMembers;

/**
 * @extends JsonObjectType<GrantedMembers>
 */
final class GrantedMembersType extends JsonObjectType
{
    public const string NAME = 'beffroi_granted_members';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return GrantedMembers::fromDocument($data);
    }

    protected function expects(): string
    {
        return GrantedMembers::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
