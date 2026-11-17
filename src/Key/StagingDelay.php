<?php

declare(strict_types=1);

namespace App\Key;

use DateInterval;
use function is_int;
use function preg_match;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * How long a staged key is published before it signs: unless configured, the max-age of the JWK Set, which no copy a
 * relying party holds is older than, plus the refetch cooldown of oidc_login for a copy that came late.
 */
final readonly class StagingDelay
{
    /**
     * OidcSignatureVerifier::ROTATION_COOLDOWN of Symfony's oidc_login, in seconds.
     */
    public const int ROTATION_COOLDOWN = 60;

    /**
     * @param int|string|null $configured seconds, as digits from the environment, or null or "" for the derived delay
     *
     * @throws InvalidConfigurationException
     */
    public static function seconds(int|string|null $configured, int $jwksMaxAge): int
    {
        if ($configured === null || $configured === '') {
            return $jwksMaxAge + self::ROTATION_COOLDOWN;
        }
        if (is_int($configured) ? $configured < 0 : preg_match('/^\d+$/D', $configured) !== 1) {
            throw new InvalidConfigurationException(sprintf(
                'The "beffroi" configuration key "staging_delay" is a number of seconds, or nothing for the max-age of the JWK Set plus %d, "%s" given.',
                self::ROTATION_COOLDOWN,
                $configured,
            ));
        }

        return (int) $configured;
    }

    /**
     * @param int|string|null $configured seconds, as digits from the environment, or null or "" for the derived delay
     *
     * @throws InvalidConfigurationException
     */
    public static function interval(int|string|null $configured, int $jwksMaxAge): DateInterval
    {
        return new DateInterval('PT' . self::seconds($configured, $jwksMaxAge) . 'S');
    }
}
