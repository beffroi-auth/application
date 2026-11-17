<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Saml\Domain\SingleLogout\LogoutProgress;

/**
 * @extends JsonObjectType<LogoutProgress>
 */
final class LogoutProgressType extends JsonObjectType
{
    public const string NAME = 'beffroi_saml_logout_progress';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return LogoutProgress::fromDocument($data);
    }

    protected function expects(): string
    {
        return LogoutProgress::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
