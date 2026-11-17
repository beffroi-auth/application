<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Session\Device;

/**
 * @extends JsonObjectType<Device>
 */
final class DeviceType extends JsonObjectType
{
    public const string NAME = 'beffroi_device';

    protected function encode(object $value): array
    {
        return $value->toDocument();
    }

    protected function decode(array $data): object
    {
        return Device::fromDocument($data);
    }

    protected function expects(): string
    {
        return Device::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
