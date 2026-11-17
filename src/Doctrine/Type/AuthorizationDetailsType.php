<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Beffroi\Oidc\Domain\AuthorizationDetails\AuthorizationDetails;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use Beffroi\Oidc\Port\AuthorizationDetailsReaderInterface;
use function is_string;
use function json_encode;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use JsonException;
use LogicException;

final class AuthorizationDetailsType extends Type implements NeedsAuthorizationDetailTypesInterface
{
    public const string NAME = 'beffroi_authorization_details';

    private static ?AuthorizationDetailsReaderInterface $types = null;

    public static function resolveWith(AuthorizationDetailsReaderInterface $types): void
    {
        self::$types = $types;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonbTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! $value instanceof AuthorizationDetails) {
            throw InvalidType::new($value, self::NAME, ['null', AuthorizationDetails::class]);
        }

        try {
            return json_encode($value->toClaim(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AuthorizationDetails
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }
        $types =
            self::$types ?? throw new LogicException('The authorization detail types have not been handed to the DBAL type; the kernel does it when it boots.');

        try {
            return $types->fromJson($value);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }
}
