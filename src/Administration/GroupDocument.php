<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Core\Domain\Group\GroupMembership;
use Beffroi\Oidc\Domain\Administration\CountedGroup;

/**
 * A group as the administration API writes it, and a member of it: the person by their identifier and username, and
 * the source of their membership, a directory's being taken back by that directory alone.
 */
final class GroupDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function of(CountedGroup $counted): array
    {
        return [
            'id' => $counted->group->id->toRfc4122(),
            'name' => $counted->group->name,
            'source' => $counted->group->source->value,
            'created_at' => AdministrationAnswer::moment($counted->group->createdAt),
            'members' => $counted->members,
            'administers' => $counted->administers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function member(GroupMembership $membership): array
    {
        return [
            'person' => [
                'id' => $membership->user->id->toRfc4122(),
                'username' => $membership->user->username,
            ],
            'source' => $membership->source->value,
            'added_at' => AdministrationAnswer::moment($membership->addedAt),
        ];
    }
}
