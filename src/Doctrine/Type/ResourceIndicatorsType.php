<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Beffroi\Oidc\Domain\Resource\ResourceIndicators;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use function is_string;

final class ResourceIndicatorsType extends Type
{
    public const string NAME = 'beffroi_resources';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! $value instanceof ResourceIndicators) {
            throw InvalidType::new($value, self::NAME, ['null', ResourceIndicators::class]);
        }

        return $value->toDocument();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ResourceIndicators
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        try {
            return ResourceIndicators::fromDocument($value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
