<?php

declare(strict_types=1);

namespace App\Http;

use DateInterval;
use DateTimeImmutable;
use Beffroi\Core\Domain\Credential\TotpProvisioning;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use function is_array;
use function is_int;
use function is_string;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Sealed by the KMS of the tenant: a session store is a disk, and the secret exists in clear in memory only.
 */
final readonly class TotpProvisioningStore
{
    public const string KEY = 'beffroi.totp.provisioning';

    public const string LIFETIME = 'PT10M';

    public function __construct(
        private TotpProvisioningSealer $sealer,
    ) {
    }

    public function store(SessionInterface $session, User $user, TotpProvisioning $provisioning, DateTimeImmutable $now): void
    {
        $session->set(self::KEY, [
            'user' => $user->id->toRfc4122(),
            'expires_at' => $now->add(new DateInterval(self::LIFETIME))->getTimestamp(),
            'sealed' => $this->sealer->seal($user, $provisioning),
        ]);
    }

    /**
     * @throws KmsUnavailableException when the KMS does not answer, the provisioning being kept
     */
    public function provisioning(SessionInterface $session, User $user, DateTimeImmutable $now): ?TotpProvisioning
    {
        $stored = $session->get(self::KEY);
        if (! is_array($stored)) {
            return null;
        }
        $expiresAt = $stored['expires_at'] ?? null;
        $sealed = $stored['sealed'] ?? null;
        if (($stored['user'] ?? null) !== $user->id->toRfc4122() || ! is_int($expiresAt) || $now->getTimestamp() >= $expiresAt || ! is_string($sealed)) {
            $session->remove(self::KEY);

            return null;
        }
        try {
            return $this->sealer->unseal($user, $sealed);
        } catch (RuntimeException|InvalidArgumentException) {
            $session->remove(self::KEY);

            return null;
        }
    }

    public function forget(SessionInterface $session): void
    {
        $session->remove(self::KEY);
    }
}
