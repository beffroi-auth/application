<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Port\PasswordAlgorithmsInterface;
use Beffroi\Oidc\Application\Client\StoredClientSecret;
use Beffroi\Oidc\Domain\Client\ClientSecretInterface;
use LogicException;

/**
 * @extends JsonObjectType<ClientSecretInterface>
 */
final class ClientSecretType extends JsonObjectType implements NeedsPasswordAlgorithmsInterface
{
    public const string NAME = 'beffroi_client_secret';

    private static ?PasswordAlgorithmsInterface $algorithms = null;

    public static function resolveWith(PasswordAlgorithmsInterface $algorithms): void
    {
        self::$algorithms = $algorithms;
    }

    protected function encode(object $value): array
    {
        return self::secrets()->document($value);
    }

    protected function decode(array $data): object
    {
        return self::secrets()->of($data);
    }

    protected function expects(): string
    {
        return ClientSecretInterface::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    private static function secrets(): StoredClientSecret
    {
        return new StoredClientSecret(
            self::$algorithms ?? throw new LogicException('The password algorithms have not been handed to the DBAL type; the kernel does it when it boots.'),
        );
    }
}
