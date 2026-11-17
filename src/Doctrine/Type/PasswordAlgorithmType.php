<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Credential\PasswordAlgorithmInterface;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\UnsupportedAlgorithmException;
use Beffroi\Core\Port\PasswordAlgorithmsInterface;
use LogicException;

/**
 * @extends StringObjectType<PasswordAlgorithmInterface>
 */
final class PasswordAlgorithmType extends StringObjectType implements NeedsPasswordAlgorithmsInterface
{
    public const string NAME = 'beffroi_password_algorithm';

    private static ?PasswordAlgorithmsInterface $algorithms = null;

    public static function resolveWith(PasswordAlgorithmsInterface $algorithms): void
    {
        self::$algorithms = $algorithms;
    }

    protected function encode(object $value): string
    {
        return $value->name();
    }

    protected function decode(string $value): object
    {
        $algorithms =
            self::$algorithms ?? throw new LogicException('The password algorithms have not been handed to the DBAL type; the kernel does it when it boots.');

        try {
            return $algorithms->named($value);
        } catch (UnsupportedAlgorithmException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }
    }

    protected function expects(): string
    {
        return PasswordAlgorithmInterface::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
