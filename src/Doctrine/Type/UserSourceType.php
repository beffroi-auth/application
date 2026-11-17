<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Identity\UserSource;
use Beffroi\Core\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends JsonObjectType<UserSource>
 */
final class UserSourceType extends JsonObjectType
{
    public const string NAME = 'beffroi_user_source';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof UserSource && $value->isLocal()) {
            return null;
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): UserSource
    {
        return parent::convertToPHPValue($value, $platform) ?? UserSource::local();
    }

    protected function encode(object $value): array
    {
        return $value->toDocument() ?? throw new InvalidArgumentException('A local source writes no document.');
    }

    protected function decode(array $data): object
    {
        return UserSource::fromDocument($data);
    }

    protected function expects(): string
    {
        return UserSource::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
