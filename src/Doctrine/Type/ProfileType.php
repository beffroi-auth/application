<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Identity\Profile;

/**
 * @extends JsonObjectType<Profile>
 */
final class ProfileType extends JsonObjectType
{
    public const string NAME = 'beffroi_profile';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return Profile::fromDocument($data);
    }

    protected function expects(): string
    {
        return Profile::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
