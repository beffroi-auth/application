<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use Beffroi\Core\Domain\Credential\PasskeyCredential;
use Beffroi\Core\Port\PasskeyUsesInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use function sprintf;
use Webauthn\AuthenticatorData;

/**
 * A copy of the credential takes the use first, so that its rules refuse the use before the row is written.
 */
final readonly class DoctrinePasskeyUses implements PasskeyUsesInterface
{
    public function __construct(
        private ConditionalCredentialWrite $rows,
    ) {
    }

    public function recordUse(PasskeyCredential $credential, AuthenticatorData $data, DateTimeImmutable $now): bool
    {
        if ($credential->signCountRegresses($data)) {
            return false;
        }
        $used = clone $credential;
        $used->recordUse($data, $now);
        $counter = $this->rows->column($credential, 'signCount');
        $written = $this->rows->written(
            $credential,
            sprintf(
                '%s = :count, %s = :backedUp, %s = :usedAt',
                $counter,
                $this->rows->column($credential, 'backedUp'),
                $this->rows->column($credential, 'lastUsedAt'),
            ),
            // Two counters at zero do not regress (decision 0034).
            sprintf('%s %s :count', $counter, $used->signCount === 0 ? '=' : '<'),
            [
                'count' => $used->signCount,
                'backedUp' => $used->backedUp,
                'usedAt' => $used->lastUsedAt,
            ],
            [
                'count' => Types::BIGINT,
                'backedUp' => Types::BOOLEAN,
                'usedAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
        if ($written) {
            $credential->recordUse($data, $now);
        }

        return $written;
    }
}
