<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Beffroi\Core\Domain\Notification\ChosenChannels;
use Beffroi\Core\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * @extends JsonObjectType<ChosenChannels>
 */
final class ChosenChannelsType extends JsonObjectType
{
    public const string NAME = 'beffroi_chosen_channels';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof ChosenChannels && $value->isNone()) {
            return null;
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ChosenChannels
    {
        return parent::convertToPHPValue($value, $platform) ?? ChosenChannels::none();
    }

    protected function encode(object $value): array
    {
        return $value->toDocument() ?? throw new InvalidArgumentException('A person who chose nothing writes no document.');
    }

    protected function decode(array $data): object
    {
        return ChosenChannels::fromDocument($data);
    }

    protected function expects(): string
    {
        return ChosenChannels::class;
    }

    protected function name(): string
    {
        return self::NAME;
    }
}
