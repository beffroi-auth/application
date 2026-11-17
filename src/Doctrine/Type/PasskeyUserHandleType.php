<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Credential\PasskeyUserHandle;

/**
 * @extends StringObjectType<PasskeyUserHandle>
 */
final class PasskeyUserHandleType extends StringObjectType
{
    public const string NAME = 'beffroi_passkey_user_handle';

    protected function encode(object $value): string
    {
        return $value->toDocument();
    }

    protected function decode(string $value): object
    {
        return PasskeyUserHandle::fromDocument($value);
    }

    protected function expects(): string
    {
        return PasskeyUserHandle::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
