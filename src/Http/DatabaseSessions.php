<?php

declare(strict_types=1);

namespace App\Http;

use Doctrine\DBAL\Connection;
use function hash_hkdf;
use function hash_hmac;
use LogicException;
use function random_bytes;
use PDO;
use SensitiveParameter;
use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_MACBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function strlen;
use function substr;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Decision 0053: the sessions of the browsers in the database every process of a deployment shares, each row
 * keyed by an HMAC of the identifier of its session, which is the cookie, and its data sealed, so that a dump of
 * the database signs nobody in and names nobody.
 */
final class DatabaseSessions extends PdoSessionHandler
{
    public const string TABLE = 'beffroi_session';

    private readonly string $identifierKey;

    private readonly string $dataKey;

    /**
     * @param array<string, mixed> $options of PdoSessionHandler
     */
    public function __construct(
        #[SensitiveParameter]
        PDO|string $database,
        #[SensitiveParameter]
        string $secret,
        #[SensitiveParameter]
        array $options = [],
    ) {
        parent::__construct($database, ['db_table' => self::TABLE, ...$options]);
        $this->identifierKey = hash_hkdf('sha256', $secret, 32, 'beffroi session identifier');
        $this->dataKey = hash_hkdf('sha256', $secret, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'beffroi session data');
    }

    /**
     * On the connection Doctrine holds, the database it migrates, under advisory locks, which a transaction of
     * Doctrine does not disturb as the lock of a transaction would.
     *
     * @throws LogicException when Doctrine holds no PDO connection
     */
    public static function onTheConnectionOf(Connection $doctrine, #[SensitiveParameter] string $secret): self
    {
        $native = $doctrine->getNativeConnection();
        if (! $native instanceof PDO) {
            throw new LogicException('The sessions are kept on a PDO connection to PostgreSQL.');
        }

        return new self($native, $secret, ['lock_mode' => self::LOCK_ADVISORY]);
    }

    /**
     * A row that does not open under the key of this deployment, written under another secret, is no session.
     */
    protected function doRead(#[SensitiveParameter] string $sessionId): string
    {
        $sealed = parent::doRead($this->key($sessionId));
        if (strlen($sealed) < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
            return '';
        }
        $data = sodium_crypto_secretbox_open(
            substr($sealed, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($sealed, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->dataKey,
        );

        return $data === false ? '' : $data;
    }

    protected function doWrite(#[SensitiveParameter] string $sessionId, #[SensitiveParameter] string $data): bool
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return parent::doWrite($this->key($sessionId), $nonce . sodium_crypto_secretbox($data, $nonce, $this->dataKey));
    }

    protected function doDestroy(#[SensitiveParameter] string $sessionId): bool
    {
        return parent::doDestroy($this->key($sessionId));
    }

    public function updateTimestamp(#[SensitiveParameter] string $sessionId, string $data): bool
    {
        return parent::updateTimestamp($this->key($sessionId), $data);
    }

    private function key(#[SensitiveParameter] string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, $this->identifierKey);
    }
}
