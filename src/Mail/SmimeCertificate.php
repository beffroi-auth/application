<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Exception\InvalidArgumentException;
use DateTimeImmutable;
use function explode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function openssl_pkey_get_details;
use function openssl_pkey_get_public;
use function openssl_x509_export;
use function openssl_x509_fingerprint;
use function openssl_x509_parse;
use function openssl_x509_read;
use const OPENSSL_KEYTYPE_RSA;
use OpenSSLCertificate;
use function preg_match_all;
use function str_starts_with;
use function strtoupper;
use function substr;
use function trim;

/**
 * One X.509 certificate of S/MIME (RFC 8550), read by the openssl extension: the addresses it names, what its key may be
 * used for, and its dates.
 */
final readonly class SmimeCertificate
{
    /**
     * RFC 8551 §4.1, §6: no RSA key under 2048 bits protects a message.
     */
    public const int SMALLEST_RSA_KEY = 2048;

    /**
     * @param array<array-key, mixed> $parsed what openssl_x509_parse() reads of it
     */
    private function __construct(
        private OpenSSLCertificate $certificate,
        private array $parsed,
    ) {
    }

    /**
     * @throws InvalidArgumentException when it is not one PEM certificate the extension reads
     */
    public static function of(string $pem): self
    {
        $certificate = @openssl_x509_read($pem);
        $parsed = $certificate === false ? false : openssl_x509_parse($certificate);
        if ($certificate === false || ! is_array($parsed)) {
            throw new InvalidArgumentException('This is not a PEM certificate.');
        }

        return new self($certificate, $parsed);
    }

    /**
     * RFC 7468 §5
     *
     * @return list<string> each certificate of a PEM document, in its order
     */
    public static function blocksOf(string $pem): array
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----[A-Za-z0-9+\/=\s]+-----END CERTIFICATE-----/', $pem, $blocks);

        return $blocks[0];
    }

    public function pem(): string
    {
        openssl_x509_export($this->certificate, $pem);

        return is_string($pem) ? $pem : '';
    }

    /**
     * The SHA-256 of its DER, in uppercase hexadecimal.
     */
    public function fingerprint(): string
    {
        return strtoupper((string) openssl_x509_fingerprint($this->certificate, 'sha256'));
    }

    /**
     * RFC 8550 §3: the rfc822Name of its subject alternative names, then the emailAddress of its subject.
     *
     * @return list<EmailAddress> the addresses this extension reads as addresses, the others left out
     */
    public function addresses(): array
    {
        $named = [];
        $alternatives = $this->extension('subjectAltName');
        foreach ($alternatives === null ? [] : explode(',', $alternatives) as $alternative) {
            $alternative = trim($alternative);
            if (str_starts_with($alternative, 'email:')) {
                $named[] = substr($alternative, 6);
            }
        }
        $subject = $this->parsed['subject'] ?? [];
        $inSubject = is_array($subject) ? $subject['emailAddress'] ?? [] : [];
        foreach (is_array($inSubject) ? $inSubject : [$inSubject] as $address) {
            if (is_string($address)) {
                $named[] = $address;
            }
        }
        $addresses = [];
        foreach ($named as $address) {
            try {
                $addresses[] = new EmailAddress($address);
            } catch (InvalidArgumentException) {
                // A name this provider cannot write to names nobody it writes to.
            }
        }

        return $addresses;
    }

    /**
     * RFC 8550 §4.4.2, §4.4.4: a key usage or an extended key usage it carries bounds what its key may do, and one it
     * does not carry bounds nothing.
     *
     * @param string $usage the name the openssl extension gives a key usage ("Digital Signature", "Key Encipherment")
     */
    public function allows(string $usage): bool
    {
        $usages = $this->extension('keyUsage');
        if ($usages !== null && ! in_array($usage, self::listed($usages), true)) {
            return false;
        }
        $extended = $this->extension('extendedKeyUsage');

        return (
            $extended === null
            || in_array('E-mail Protection', self::listed($extended), true)
            || in_array('Any Extended Key Usage', self::listed($extended), true)
        );
    }

    public function hasAStrongEnoughRsaKey(): bool
    {
        $public = openssl_pkey_get_public($this->certificate);
        $details = $public === false ? false : openssl_pkey_get_details($public);

        return (
            is_array($details)
            && ($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA
            && is_int($details['bits'] ?? null)
            && $details['bits'] >= self::SMALLEST_RSA_KEY
        );
    }

    /**
     * The PEM of its public key, which is the same string for the same key.
     */
    public function publicKey(): string
    {
        $public = openssl_pkey_get_public($this->certificate);
        $details = $public === false ? false : openssl_pkey_get_details($public);

        return is_array($details) && is_string($details['key'] ?? null) ? $details['key'] : '';
    }

    public function notBefore(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . self::moment($this->parsed['validFrom_time_t'] ?? null));
    }

    public function notAfter(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . self::moment($this->parsed['validTo_time_t'] ?? null));
    }

    public function isValidAt(DateTimeImmutable $moment): bool
    {
        return $this->notBefore() <= $moment && $moment < $this->notAfter();
    }

    private function extension(string $name): ?string
    {
        $extensions = $this->parsed['extensions'] ?? [];
        $value = is_array($extensions) ? $extensions[$name] ?? null : null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function listed(string $usages): array
    {
        $listed = [];
        foreach (explode(',', $usages) as $usage) {
            $listed[] = trim($usage);
        }

        return $listed;
    }

    private static function moment(mixed $timestamp): int
    {
        return is_int($timestamp) ? $timestamp : 0;
    }
}
