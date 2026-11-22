<?php

declare(strict_types=1);

namespace App\Mail\OpenPgp;

use Beffroi\Core\Exception\InvalidArgumentException;
use function chr;
use DateTimeImmutable;
use function bin2hex;
use function hash;
use function hex2bin;
use function implode;
use function is_string;
use Jose\Component\Core\Util\Base64UrlSafe;
use function openssl_sign;
use const OPENSSL_ALGO_SHA256;
use OpenSSLAsymmetricKey;
use function pack;
use function preg_match;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * A version 4 OpenPGP key over an RSA key of the tenant (RFC 9580 §5.5.2), and the two signatures this provider makes
 * with it: the certification of its user ID (§5.2.1.4) and the detached signature of a canonical text (§5.2.1.2), the
 * one PGP/MIME signs a part with (RFC 3156 §5). The RSA signature is OpenSSL's, over SHA-256 (§5.2.2, §9.5).
 */
final readonly class OpenPgpRsaKey
{
    private const int RSA = 1;

    private const int SHA256 = 8;

    private const int SIGNATURE_CREATION_TIME = 2;

    private const int ISSUER_KEY_ID = 16;

    private const int KEY_FLAGS = 27;

    private const int ISSUER_FINGERPRINT = 33;

    private const int CERTIFY_AND_SIGN = 0x03;

    private const int CANONICAL_TEXT = 0x01;

    private const int POSITIVE_CERTIFICATION = 0x13;

    private function __construct(
        private string $publicKeyBody,
        private string $fingerprint,
    ) {
    }

    /**
     * @param array<string, string> $jwk an RSA JWK, of which "n" and "e" are read
     *
     * @throws InvalidArgumentException when it carries no RSA modulus and exponent
     */
    public static function ofJwk(array $jwk, DateTimeImmutable $createdAt): self
    {
        $modulus = $jwk['n'] ?? null;
        $exponent = $jwk['e'] ?? null;
        if (($jwk['kty'] ?? null) !== 'RSA' || ! is_string($modulus) || ! is_string($exponent)) {
            throw new InvalidArgumentException('An OpenPGP key of the tenant is an RSA key.');
        }
        $body =
            chr(4)
            . pack('N', $createdAt->getTimestamp())
            . chr(self::RSA)
            . OpenPgpPackets::mpi(Base64UrlSafe::decodeNoPadding($modulus))
            . OpenPgpPackets::mpi(Base64UrlSafe::decodeNoPadding($exponent));

        return new self($body, hash('sha1', "\x99" . pack('n', strlen($body)) . $body, true));
    }

    /**
     * The fingerprint alone, which names the key in a signature: what is signed with it later is signed with what the
     * fingerprint names, the key itself having been certified once.
     *
     * @throws InvalidArgumentException when it is not the forty hexadecimal digits of a version 4 fingerprint
     */
    public static function signingAs(string $fingerprint): self
    {
        if (preg_match('/^[0-9A-F]{40}$/', $fingerprint) !== 1) {
            throw new InvalidArgumentException('A version 4 OpenPGP fingerprint is forty hexadecimal digits.');
        }

        return new self('', (string) hex2bin($fingerprint));
    }

    /**
     * RFC 9580 §5.5.4.2
     */
    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * RFC 9580 §10.1: the public key packet, the user ID and the certification that binds them.
     *
     * @throws InvalidArgumentException when OpenSSL does not sign
     */
    public function transferablePublicKey(OpenSSLAsymmetricKey $private, string $userId, DateTimeImmutable $now): string
    {
        $hashed = "\x99" . pack('n', strlen($this->publicKeyBody)) . $this->publicKeyBody . "\xB4" . pack('N', strlen($userId)) . $userId;

        return (
            OpenPgpPackets::packet(OpenPgpPackets::PUBLIC_KEY, $this->publicKeyBody)
            . OpenPgpPackets::packet(OpenPgpPackets::USER_ID, $userId)
            . $this->signature($private, self::POSITIVE_CERTIFICATION, $hashed, [
                OpenPgpPackets::subpacket(self::SIGNATURE_CREATION_TIME, pack('N', $now->getTimestamp())),
                OpenPgpPackets::subpacket(self::KEY_FLAGS, chr(self::CERTIFY_AND_SIGN)),
                OpenPgpPackets::subpacket(self::ISSUER_FINGERPRINT, chr(4) . $this->fingerprint),
            ])
        );
    }

    /**
     * RFC 9580 §5.2.1.2: the text with its line endings made CR LF, which is what a reader hashes again.
     *
     * @throws InvalidArgumentException when OpenSSL does not sign
     */
    public function detachedTextSignature(OpenSSLAsymmetricKey $private, string $text, DateTimeImmutable $now): string
    {
        $canonical = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $text));

        return $this->signature($private, self::CANONICAL_TEXT, $canonical, [
            OpenPgpPackets::subpacket(self::SIGNATURE_CREATION_TIME, pack('N', $now->getTimestamp())),
            OpenPgpPackets::subpacket(self::ISSUER_FINGERPRINT, chr(4) . $this->fingerprint),
        ]);
    }

    /**
     * RFC 9580 §5.2.3, §5.2.4: the hashed subpackets, then the version 4 trailer, after what is signed.
     *
     * @param list<string> $subpackets
     *
     * @throws InvalidArgumentException
     */
    private function signature(OpenSSLAsymmetricKey $private, int $type, string $signed, array $subpackets): string
    {
        $hashedArea = implode('', $subpackets);
        $header = pack('CCCCn', 4, $type, self::RSA, self::SHA256, strlen($hashedArea)) . $hashedArea;
        $input = $signed . $header . "\x04\xFF" . pack('N', strlen($header));
        if (! openssl_sign($input, $signature, $private, OPENSSL_ALGO_SHA256) || ! is_string($signature)) {
            throw new InvalidArgumentException(sprintf('OpenSSL did not sign with the OpenPGP key %s.', bin2hex($this->fingerprint)));
        }
        $unhashedArea = OpenPgpPackets::subpacket(self::ISSUER_KEY_ID, substr($this->fingerprint, -8));

        return OpenPgpPackets::packet(
            OpenPgpPackets::SIGNATURE,
            $header . pack('n', strlen($unhashedArea)) . $unhashedArea . substr(hash('sha256', $input, true), 0, 2) . OpenPgpPackets::mpi($signature),
        );
    }
}
