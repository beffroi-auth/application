<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Token\ConfirmationInterface;
use Beffroi\Oidc\Port\ConfirmationReaderInterface;
use LogicException;

/**
 * @extends JsonbObjectType<ConfirmationInterface>
 */
final class ConfirmationType extends JsonbObjectType implements NeedsConfirmationReaderInterface
{
    public const string NAME = 'beffroi_confirmation';

    private static ?ConfirmationReaderInterface $confirmations = null;

    public static function resolveWith(ConfirmationReaderInterface $confirmations): void
    {
        self::$confirmations = $confirmations;
    }

    protected function encode(object $value): array
    {
        return $value->toMembers();
    }

    protected function decode(array $data): object
    {
        $confirmations =
            self::$confirmations ?? throw new LogicException('The confirmation kinds have not been handed to the DBAL type; the kernel does it when it boots.');

        return $confirmations->fromMembers($data);
    }

    protected function expects(): string
    {
        return ConfirmationInterface::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
