<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\MailEncryptionKey;
use Beffroi\Core\Domain\Identity\MailEncryptionRefusal;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\MailEncryptionRefusedException;
use function count;
use const OPENSSL_CIPHER_AES_256_CBC;
use function strlen;
use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Message;

/**
 * RFC 8551 §3.3: the mail enveloped for the certificate the person gave, under AES-256-CBC. SMimeEncrypter writes an
 * EnvelopedData through openssl_pkcs7_encrypt(), never the AuthEnvelopedData of RFC 5083 that AES-GCM needs (§2.7).
 */
final readonly class Smime implements MailKeyReaderInterface, MailEncrypterInterface
{
    public const string KIND = 'smime';

    /**
     * In octets: a certificate is a few kilobytes, and what a person posts is read whole.
     */
    public const int LONGEST = 65_536;

    public function kind(): string
    {
        return self::KIND;
    }

    public function read(string $material): MailEncryptionKey
    {
        $blocks = strlen($material) > self::LONGEST ? [] : SmimeCertificate::blocksOf($material);
        if (count($blocks) !== 1) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'An S/MIME certificate is given alone, in PEM.');
        }
        try {
            $certificate = SmimeCertificate::of($blocks[0]);
        } catch (InvalidArgumentException) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'The openssl extension does not read this certificate.');
        }
        // openssl_pkcs7_encrypt() transports its content key to RSA alone (RFC 8551 §2.3).
        if (! $certificate->hasAStrongEnoughRsaKey() || ! $certificate->allows('Key Encipherment')) {
            throw new MailEncryptionRefusedException(
                MailEncryptionRefusal::NotForEncryption,
                'This certificate does not carry an RSA key of 2048 bits at least that may encrypt mail.',
            );
        }

        return new MailEncryptionKey(
            self::KIND,
            $certificate->pem(),
            $certificate->fingerprint(),
            $certificate->addresses(),
            $certificate->notBefore(),
            $certificate->notAfter(),
        );
    }

    public function encrypt(Message $mail, MailEncryptionKey $key): Message
    {
        return TemporaryFiles::holding(
            [$key->material],
            static fn (array $paths): Message => new SMimeEncrypter($paths[0], OPENSSL_CIPHER_AES_256_CBC)->encrypt($mail),
        );
    }
}
