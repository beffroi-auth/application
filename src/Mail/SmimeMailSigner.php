<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Key\MailSigningKey;
use Beffroi\Core\Exception\InvalidArgumentException;
use function bin2hex;
use function implode;
use function is_string;
use function openssl_pkey_export;
use const OPENSSL_CIPHER_AES_256_CBC;
use const PKCS7_DETACHED;
use const PKCS7_NOOLDMIMETYPE;
use function random_bytes;
use SensitiveParameter;
use function array_slice;
use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;

/**
 * RFC 8551 §3.5.3: multipart/signed, its signature an application/pkcs7-signature part, the certificate of the key and
 * the chain above it carried in the signature. SMimeSigner reads the private key from a file: what it reads is that key
 * encrypted under a passphrase drawn for this one signature, which no file holds, so the key is in clear in this process
 * alone.
 */
final readonly class SmimeMailSigner implements MailSignerInterface
{
    public function kind(): string
    {
        return Smime::KIND;
    }

    public function sign(Message $mail, MailSigningKey $key, #[SensitiveParameter] array $privateJwk): Message
    {
        $certificates = SmimeCertificate::blocksOf($key->published);
        if ($certificates === []) {
            throw new RuntimeException('The S/MIME key of the tenant holds no certificate.');
        }
        $passphrase = bin2hex(random_bytes(32));
        try {
            $exported = openssl_pkey_export(RsaJwk::privateKey($privateJwk), $encrypted, $passphrase, [
                'encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC,
            ]);
        } catch (InvalidArgumentException $refusal) {
            throw new RuntimeException($refusal->getMessage(), 0, $refusal);
        }
        if (! $exported || ! is_string($encrypted)) {
            throw new RuntimeException('OpenSSL does not export the S/MIME key of the tenant.');
        }
        $chain = array_slice($certificates, 1);
        $files = $chain === [] ? [$certificates[0], $encrypted] : [$certificates[0], $encrypted, implode("\n", $chain) . "\n"];

        return TemporaryFiles::holding(
            $files,
            static fn (array $paths): Message => new SMimeSigner(
                $paths[0],
                $paths[1],
                $passphrase,
                $paths[2] ?? null,
                PKCS7_DETACHED | PKCS7_NOOLDMIMETYPE,
            )->sign($mail),
        );
    }
}
