<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Tenant\Issuer;

/**
 * @extends StringObjectType<Issuer>
 */
final class IssuerType extends StringObjectType
{
    public const string NAME = 'beffroi_issuer';

    protected function encode(object $value): string
    {
        return $value->value;
    }

    protected function decode(string $value): object
    {
        return new Issuer($value);
    }

    protected function expects(): string
    {
        return Issuer::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
