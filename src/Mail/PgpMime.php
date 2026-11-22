<?php

declare(strict_types=1);

namespace App\Mail;

use Beffroi\Core\Domain\Identity\EmailAddress;
use Beffroi\Core\Domain\Identity\MailEncryptionKey;
use Beffroi\Core\Domain\Identity\MailEncryptionRefusal;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\MailEncryptionRefusedException;
use function count;
use DateTimeImmutable;
use function explode;
use function hex2bin;
use function in_array;
use function preg_match;
use function preg_replace_callback;
use function str_contains;
use function strlen;
use function substr_count;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use function trim;

/**
 * RFC 3156 §4: the mail encrypted by gpg to the OpenPGP key the person gave, named by its fingerprint so that no other
 * key of the same address is chosen, under AES-256.
 */
final readonly class PgpMime implements MailKeyReaderInterface, MailEncrypterInterface
{
    public const string KIND = 'pgp';

    /**
     * In octets: a key with its user IDs and a few certifications, read whole.
     */
    public const int LONGEST = 65_536;

    public function __construct(
        private Gnupg $gnupg = new Gnupg(),
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    /**
     * GnuPG doc/DETAILS: field 2 the validity, 6 and 7 the creation and the expiration, 10 the user ID or the
     * fingerprint, 12 the capabilities, uppercase for the key as a whole.
     */
    public function read(string $material): MailEncryptionKey
    {
        if (strlen($material) > self::LONGEST || substr_count($material, '-----BEGIN PGP PUBLIC KEY BLOCK-----') !== 1) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'An OpenPGP public key is given alone, ASCII-armored.');
        }
        try {
            $listing = $this->gnupg->listing($material);
        } catch (RuntimeException) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'gpg does not read this key.');
        }
        $primary = [];
        $fingerprint = null;
        $addresses = [];
        foreach (explode("\n", $listing) as $line) {
            $fields = explode(':', $line);
            if ($fields[0] === 'pub') {
                $primary[] = $fields;
            } elseif ($fields[0] === 'fpr' && $fingerprint === null) {
                $fingerprint = $fields[9] ?? null;
            } elseif ($fields[0] === 'uid' && ($fields[1] ?? '') !== 'r') {
                $address = self::addressOf($fields[9] ?? '');
                if ($address !== null) {
                    $addresses[] = $address;
                }
            }
        }
        if (count($primary) !== 1 || $fingerprint === null || $fingerprint === '') {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::Unreadable, 'An OpenPGP public key is given alone.');
        }
        $key = $primary[0];
        if (in_array($key[1] ?? '', ['r', 'e'], true)) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::NotValidNow, 'This key is revoked or has expired.');
        }
        if (! str_contains($key[11] ?? '', 'E')) {
            throw new MailEncryptionRefusedException(MailEncryptionRefusal::NotForEncryption, 'This key has no subkey a mail may be encrypted to.');
        }

        return new MailEncryptionKey(
            self::KIND,
            trim($material) . "\n",
            $fingerprint,
            $addresses,
            self::moment($key[5] ?? ''),
            self::moment($key[6] ?? ''),
        );
    }

    public function encrypt(Message $mail, MailEncryptionKey $key): Message
    {
        return TemporaryFiles::holding([$key->material], function (array $paths) use ($mail, $key): Message {
            try {
                return new PgpEncrypter(['binary' => $this->gnupg->binary(), 'cipher_algorithm' => 'AES256'])->encrypt($mail, [$key->fingerprint => $paths[0]]);
            } catch (ProcessException $failure) {
                throw new RuntimeException('gpg did not encrypt the mail.', 0, $failure);
            }
        });
    }

    /**
     * A user ID is an address between angle brackets or an address alone (RFC 9580 §5.11); gpg writes a colon of it as
     * \x3a.
     */
    private static function addressOf(string $userId): ?EmailAddress
    {
        $userId = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static fn (array $escaped): string => (string) hex2bin($escaped[1]), $userId) ?? '';
        $address = preg_match('/<([^<>]+)>/', $userId, $bracketed) === 1 ? $bracketed[1] : trim($userId);
        try {
            return new EmailAddress($address);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function moment(string $timestamp): ?DateTimeImmutable
    {
        return preg_match('/^\d+$/', $timestamp) === 1 ? new DateTimeImmutable('@' . $timestamp) : null;
    }
}
