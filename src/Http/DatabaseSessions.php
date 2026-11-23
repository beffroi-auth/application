<?php

declare(strict_types=1);

namespace App\Http;

use Doctrine\DBAL\Connection;
use function hash;
use LogicException;
use PDO;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Decision 0053: the sessions of the browsers in the database every process of a deployment shares, each row
 * keyed by a hash of the identifier of its session, which is the cookie, and its data sealed as personal data is
 * (decision 0011), so that a dump of the database signs nobody in and names nobody.
 */
final class DatabaseSessions extends PdoSessionHandler
{
    public const string TABLE = 'beffroi_session';

    /**
     * The family of the data key of the tenant the data of a session is sealed under.
     */
    public const string FAMILY = 'browser_session';

    /**
     * Followed by the hash of the identifier of its row, so that a row moved under another identifier and a value sealed
     * for a column of personal data open as no session.
     */
    public const string AAD = 'beffroi:browser-session:v1:';

    /**
     * @param array<string, mixed> $options of PdoSessionHandler
     */
    public function __construct(
        #[SensitiveParameter]
        PDO|string $database,
        private readonly EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $sealer,
        #[SensitiveParameter]
        array $options = [],
    ) {
        parent::__construct($database, ['db_table' => self::TABLE, ...$options]);
    }

    /**
     * On the connection Doctrine holds, the database it migrates, under advisory locks, which a transaction of
     * Doctrine does not disturb as the lock of a transaction would.
     *
     * @throws LogicException when Doctrine holds no PDO connection
     */
    public static function onTheConnectionOf(
        Connection $doctrine,
        EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $sealer,
    ): self {
        $native = $doctrine->getNativeConnection();
        if (! $native instanceof PDO) {
            throw new LogicException('The sessions are kept on a PDO connection to PostgreSQL.');
        }

        return new self($native, $sealer, ['lock_mode' => self::LOCK_ADVISORY]);
    }

    /**
     * A row whose envelope does not open, tampered with, moved under another identifier or sealed under a data key the
     * tenant no longer holds, is no session.
     */
    protected function doRead(#[SensitiveParameter] string $sessionId): string
    {
        $key = $this->key($sessionId);
        $sealed = parent::doRead($key);
        if ($sealed === '') {
            return '';
        }
        try {
            return $this->sealer->decrypt(Envelope::fromBytes($sealed), self::AAD . $key);
        } catch (DecryptionFailedException|DataKeyNotFoundException|InvalidArgumentException) {
            return '';
        }
    }

    protected function doWrite(#[SensitiveParameter] string $sessionId, #[SensitiveParameter] string $data): bool
    {
        $key = $this->key($sessionId);

        return parent::doWrite($key, (string) $this->sealer->encrypt(self::FAMILY, $data, self::AAD . $key));
    }

    protected function doDestroy(#[SensitiveParameter] string $sessionId): bool
    {
        return parent::doDestroy($this->key($sessionId));
    }

    public function updateTimestamp(#[SensitiveParameter] string $sessionId, string $data): bool
    {
        return parent::updateTimestamp($this->key($sessionId), $data);
    }

    /**
     * A drawn secret is kept as its hash, as a client secret is.
     */
    private function key(#[SensitiveParameter] string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
