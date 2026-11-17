<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Oidc\Domain\Token\KeyConfirmation;

/**
 * @extends StringObjectType<KeyConfirmation>
 */
final class KeyConfirmationType extends StringObjectType
{
    public const string NAME = 'beffroi_key_confirmation';

    /**
     * RFC 7638 §3.1: the base64url of a SHA-256.
     */
    public const int LENGTH = 43;

    protected function encode(object $value): string
    {
        return $value->thumbprint;
    }

    protected function decode(string $value): object
    {
        return new KeyConfirmation($value);
    }

    protected function expects(): string
    {
        return KeyConfirmation::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
