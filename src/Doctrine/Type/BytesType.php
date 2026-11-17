<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Encoding\Base64Url;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use function is_string;

/**
 * Bytes as unpadded base64url text (RFC 4648 §5), where the binary types of DBAL would hand back a stream.
 */
final class BytesType extends Type
{
    public const string NAME = 'beffroi_bytes';

    public static function encode(string $bytes): string
    {
        return Base64Url::encode($bytes);
    }

    public static function decode(string $encoded): ?string
    {
        return Base64Url::decode($encoded);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return self::encode($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return self::decode($value) ?? throw ValueNotConvertible::new($value, self::NAME, 'The text is not base64url.');
    }
}
