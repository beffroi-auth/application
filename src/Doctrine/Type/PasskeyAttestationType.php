<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Application\Passkey\PasskeyAttestations;
use Beffroi\Core\Domain\Tenant\PasskeyAttestation;
use LogicException;

/**
 * @extends JsonObjectType<PasskeyAttestation>
 */
final class PasskeyAttestationType extends JsonObjectType implements NeedsPasskeyAttestationsInterface
{
    public const string NAME = 'beffroi_passkey_attestation';

    private static ?PasskeyAttestations $attestations = null;

    public static function resolveWith(PasskeyAttestations $attestations): void
    {
        self::$attestations = $attestations;
    }

    protected function encode(object $value): array
    {
        return self::attestations()->document($value);
    }

    protected function decode(array $data): object
    {
        return self::attestations()->of($data);
    }

    protected function expects(): string
    {
        return PasskeyAttestation::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }

    private static function attestations(): PasskeyAttestations
    {
        return (
            self::$attestations ?? throw new LogicException('The passkey attestation policies have not been handed to the DBAL type; the kernel does it when it boots.')
        );
    }
}
