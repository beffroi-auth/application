<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Application\Credential\StoredPasswordHash;
use Beffroi\Core\Domain\Credential\PasswordHash;
use Beffroi\Core\Port\PasswordAlgorithmsInterface;
use LogicException;

/**
 * @extends JsonbObjectType<PasswordHash>
 */
final class PasswordHashType extends JsonbObjectType implements NeedsPasswordAlgorithmsInterface
{
    public const string NAME = 'beffroi_password_hash';

    private static ?PasswordAlgorithmsInterface $algorithms = null;

    public static function resolveWith(PasswordAlgorithmsInterface $algorithms): void
    {
        self::$algorithms = $algorithms;
    }

    protected function encode(object $value): array
    {
        return self::hashes()->document($value);
    }

    protected function decode(array $data): object
    {
        return self::hashes()->of($data);
    }

    protected function expects(): string
    {
        return PasswordHash::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    private static function hashes(): StoredPasswordHash
    {
        return new StoredPasswordHash(
            self::$algorithms ?? throw new LogicException('The password algorithms have not been handed to the DBAL type; the kernel does it when it boots.'),
        );
    }
}
