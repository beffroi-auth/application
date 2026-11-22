<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Exception\InvalidArgumentException;
use function is_string;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\KeyManagement\JWKFactory;
use function openssl_pkey_export;
use function openssl_pkey_get_private;
use function openssl_pkey_new;
use OpenSSLAsymmetricKey;
use SensitiveParameter;
use Throwable;

/**
 * A private RSA key between the JWK the KMS seals (web-token/jwt-library) and the key OpenSSL signs with, in memory.
 */
final readonly class RsaJwk
{
    /**
     * The members of a private RSA JWK (RFC 7518 §6.3.2), by the name OpenSSL gives each.
     */
    private const array COMPONENTS = [
        'n' => 'n',
        'e' => 'e',
        'd' => 'd',
        'p' => 'p',
        'q' => 'q',
        'dp' => 'dmp1',
        'dq' => 'dmq1',
        'qi' => 'iqmp',
    ];

    /**
     * @throws InvalidArgumentException when it is not a private RSA key in clear
     *
     * @return array<string, string>
     */
    public static function ofPem(#[SensitiveParameter] string $pem): array
    {
        // An empty passphrase and not none: OpenSSL would otherwise ask a terminal for the one of an encrypted key.
        $private = @openssl_pkey_get_private($pem, '');
        if (! $private instanceof OpenSSLAsymmetricKey || ! openssl_pkey_export($private, $clear) || ! is_string($clear)) {
            throw new InvalidArgumentException('This is not a private key in PEM, or it is protected by a passphrase.');
        }
        try {
            $members = JWKFactory::createFromKey($clear)->all();
        } catch (Throwable) {
            $members = [];
        }
        if (($members['kty'] ?? null) !== 'RSA' || ! isset($members['d'])) {
            throw new InvalidArgumentException('A key the tenant signs its mail with is a private RSA key.');
        }
        return self::strings($members);
    }

    /**
     * @param array<string, string> $jwk
     *
     * @throws InvalidArgumentException when OpenSSL does not take it
     */
    public static function privateKey(#[SensitiveParameter] array $jwk): OpenSSLAsymmetricKey
    {
        $components = [];
        foreach (self::COMPONENTS as $member => $component) {
            $value = $jwk[$member] ?? null;
            if ($value === null) {
                throw new InvalidArgumentException('OpenSSL does not take a private RSA key without its "' . $member . '".');
            }
            $components[$component] = Base64UrlSafe::decodeNoPadding($value);
        }
        $key = openssl_pkey_new(['rsa' => $components]);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new InvalidArgumentException('OpenSSL does not take this private RSA key.');
        }

        return $key;
    }

    /**
     * @param array<array-key, mixed> $members
     *
     * @return array<string, string>
     */
    private static function strings(array $members): array
    {
        $strings = [];
        foreach ($members as $name => $value) {
            if (is_string($value)) {
                $strings[(string) $name] = $value;
            }
        }

        return $strings;
    }
}
