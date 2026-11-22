<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Tenant\TenantContact;

/**
 * @extends StringObjectType<TenantContact>
 */
final class TenantContactType extends StringObjectType
{
    public const string NAME = 'beffroi_tenant_contact';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new TenantContact($value);
    }

    protected function expects(): string
    {
        return TenantContact::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
