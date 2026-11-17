<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Session\GeoLocation;

/**
 * @extends JsonObjectType<GeoLocation>
 */
final class GeoLocationType extends JsonObjectType
{
    public const string NAME = 'beffroi_geo_location';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return GeoLocation::fromDocument($data);
    }

    protected function expects(): string
    {
        return GeoLocation::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
