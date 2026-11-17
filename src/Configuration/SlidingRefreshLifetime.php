<?php

declare(strict_types=1);

namespace App\Configuration;

use DateInterval;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final readonly class SlidingRefreshLifetime
{
    /**
     * @throws InvalidConfigurationException
     */
    public static function assertWithin(int $sliding, int $absolute): void
    {
        if ($sliding > $absolute) {
            throw new InvalidConfigurationException('The sliding lifetime of a refresh token (refresh_token_ttl) cannot exceed the absolute lifetime of its family (refresh_token_absolute_ttl).');
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function interval(int $sliding, int $absolute): DateInterval
    {
        self::assertWithin($sliding, $absolute);

        return BoundedNumber::interval($sliding, 'refresh_token_ttl');
    }
}
