<?php

declare(strict_types=1);

namespace App\Push;

use function base64_decode;
use function base64_encode;
use Beffroi\Core\Domain\Key\TenantKey;
use Beffroi\Core\Exception\InvalidArgumentException;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function rtrim;
use function sprintf;
use const STR_PAD_LEFT;
use function strtr;

/**
 * RFC 8292 §3.2, Push API §7.2, SEC 1 §2.3.3
 */
final readonly class ApplicationServerKey
{
    /**
     * RFC 7518 §6.2.1.2
     */
    public const int COORDINATE_LENGTH = 32;

    /**
     * SEC 1 §2.3.3
     */
    private const string UNCOMPRESSED = "\x04";

    /**
     * @param string $publicKey the uncompressed point, base64url
     */
    private function __construct(
        public string $kid,
        public string $publicKey,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function of(TenantKey $key): self
    {
        return new self($key->kid, self::uncompressedPoint($key->publicJwk));
    }

    /**
     * @param array<string, mixed> $jwk
     *
     * @throws InvalidArgumentException
     */
    public static function uncompressedPoint(array $jwk): string
    {
        $point = self::UNCOMPRESSED;
        foreach (['x', 'y'] as $coordinate) {
            $point .= self::octetsOf($jwk, $coordinate);
        }

        return rtrim(strtr(base64_encode($point), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $jwk
     *
     * @throws InvalidArgumentException
     */
    private static function octetsOf(array $jwk, string $coordinate): string
    {
        $value = $jwk[$coordinate] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf(
                'An application server key carries the "%s" coordinate of its point (RFC 7518 section 6.2.1.2).',
                $coordinate,
            ));
        }
        $octets = base64_decode(strtr($value, '-_', '+/'), true);
        if ($octets === false || mb_strlen($octets, '8bit') > self::COORDINATE_LENGTH) {
            throw new InvalidArgumentException('An application server key is a pair over P-256 (RFC 8292 section 3.2).');
        }

        return mb_str_pad($octets, self::COORDINATE_LENGTH, "\0", STR_PAD_LEFT, '8bit');
    }
}
