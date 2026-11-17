<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Http\ByteLimit;
use App\Http\Timeout;
use DateInterval;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The bounds of the tree checked again at runtime, where a number from the environment is first known.
 */
final readonly class BoundedNumber
{
    /**
     * @throws InvalidConfigurationException
     */
    public static function interval(int $seconds, string $key, ?int $maximum = null, int $minimum = 1): DateInterval
    {
        if ($seconds < $minimum || $maximum !== null && $seconds > $maximum) {
            throw new InvalidConfigurationException(
                $maximum === null
                    ? self::belowItsFloor($key, 'seconds', (string) $minimum, (string) $seconds)
                    : sprintf('The "beffroi" configuration key "%s" is a number of seconds from %d to %d, %d given.', $key, $minimum, $maximum, $seconds),
            );
        }

        return new DateInterval('PT' . $seconds . 'S');
    }

    /**
     * Held to a floor: HttpClient reads a "max_duration" of zero as no limit at all.
     *
     * @throws InvalidConfigurationException
     */
    public static function timeout(float $seconds, string $key): Timeout
    {
        if ($seconds < Timeout::MINIMUM) {
            throw new InvalidConfigurationException(self::belowItsFloor($key, 'seconds', (string) Timeout::MINIMUM, (string) $seconds));
        }

        return new Timeout($seconds);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function bytes(int $bytes, string $key): ByteLimit
    {
        self::assertAtLeast($bytes, 1, $key, 'bytes');

        return new ByteLimit($bytes);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function assertAtLeast(int $number, int $minimum, string $key, string $unit): void
    {
        if ($number < $minimum) {
            throw new InvalidConfigurationException(self::belowItsFloor($key, $unit, (string) $minimum, (string) $number));
        }
    }

    private static function belowItsFloor(string $key, string $unit, string $minimum, string $given): string
    {
        return sprintf('The "beffroi" configuration key "%s" is a number of %s of at least %s, %s given.', $key, $unit, $minimum, $given);
    }
}
