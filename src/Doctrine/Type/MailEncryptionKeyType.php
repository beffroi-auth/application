<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Identity\MailEncryptionKey;

/**
 * @extends JsonObjectType<MailEncryptionKey>
 */
final class MailEncryptionKeyType extends JsonObjectType
{
    public const string NAME = 'beffroi_mail_encryption_key';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return MailEncryptionKey::fromDocument($data);
    }

    protected function expects(): string
    {
        return MailEncryptionKey::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
