<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Declares the column as DBAL's JsonbType does: doctrine/dbal 4.3 deprecates the "jsonb" platform option.
 *
 * @template T of object
 *
 * @extends JsonObjectType<T>
 */
abstract class JsonbObjectType extends JsonObjectType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonbTypeDeclarationSQL($column);
    }
}
