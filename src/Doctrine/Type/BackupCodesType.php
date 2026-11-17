<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use function array_is_list;
use Beffroi\Core\Application\Credential\StoredBackupCodes;
use Beffroi\Core\Domain\Credential\BackupCode;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Port\PasswordAlgorithmsInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use JsonException;
use LogicException;

final class BackupCodesType extends Type implements NeedsPasswordAlgorithmsInterface
{
    public const string NAME = 'beffroi_backup_codes';

    private static ?PasswordAlgorithmsInterface $algorithms = null;

    public static function resolveWith(PasswordAlgorithmsInterface $algorithms): void
    {
        self::$algorithms = $algorithms;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'list<' . BackupCode::class . '>']);
        }
        $codes = [];
        foreach ($value as $code) {
            if (! $code instanceof BackupCode) {
                throw InvalidType::new($value, self::NAME, ['null', 'list<' . BackupCode::class . '>']);
            }
            $codes[] = $code;
        }

        try {
            return json_encode(self::codes()->document($codes), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }

    /**
     * @return list<BackupCode>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        try {
            $decoded = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw ValueNotConvertible::new($value, self::NAME, 'A JSON list was expected.');
        }

        try {
            return self::codes()->of($decoded);
        } catch (InvalidArgumentException $exception) {
            throw ValueNotConvertible::new($value, self::NAME, $exception->getMessage(), $exception);
        }
    }

    private static function codes(): StoredBackupCodes
    {
        return new StoredBackupCodes(
            self::$algorithms ?? throw new LogicException('The password algorithms have not been handed to the DBAL type; the kernel does it when it boots.'),
        );
    }
}
