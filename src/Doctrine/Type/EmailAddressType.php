<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Identity\EmailAddress;

/**
 * @extends StringObjectType<EmailAddress>
 */
final class EmailAddressType extends StringObjectType
{
    public const string NAME = 'beffroi_email_address';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new EmailAddress($value);
    }

    protected function expects(): string
    {
        return EmailAddress::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
