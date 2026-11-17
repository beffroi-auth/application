<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Exchange\Actor;

/**
 * @extends JsonbObjectType<Actor>
 */
final class ActorType extends JsonbObjectType
{
    public const string NAME = 'beffroi_actor';

    protected function encode(object $value): array
    {
        return $value->toClaim();
    }

    protected function decode(array $data): object
    {
        return Actor::fromClaim($data);
    }

    protected function expects(): string
    {
        return Actor::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
