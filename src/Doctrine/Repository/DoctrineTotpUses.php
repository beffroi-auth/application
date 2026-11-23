<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\Type\BackupCodesType;
use Beffroi\Core\Domain\Credential\TotpCredential;
use Beffroi\Core\Port\TotpUsesInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use function sprintf;

/**
 * A copy of the credential takes the use first, so that its rules refuse the use before the row is written.
 */
final readonly class DoctrineTotpUses implements TotpUsesInterface
{
    public function __construct(
        private ConditionalCredentialWrite $rows,
    ) {
    }

    public function acceptStep(TotpCredential $credential, int $step, DateTimeImmutable $now): bool
    {
        if (! $credential->acceptsStep($step)) {
            return false;
        }
        $used = clone $credential;
        $used->recordUse($step, $now);
        $column = $this->rows->column($credential, 'lastAcceptedStep');
        $written = $this->rows->written(
            $credential,
            sprintf('%s = :step, %s = :usedAt', $column, $this->rows->column($credential, 'lastUsedAt')),
            sprintf('%s < :step', $column),
            [
                'step' => $used->lastAcceptedStep,
                'usedAt' => $used->lastUsedAt,
            ],
            [
                'step' => Types::BIGINT,
                'usedAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
        if ($written) {
            $credential->recordUse($step, $now);
        }

        return $written;
    }

    public function spendBackupCode(TotpCredential $credential, int $index, DateTimeImmutable $now): bool
    {
        if (! $credential->holdsUnspentBackupCode($index)) {
            return false;
        }
        $used = clone $credential;
        $used->spendBackupCode($index, $now);
        $column = $this->rows->column($credential, 'backupCodes');
        // The column keeps the text it was given, and the type writes one text for one list of codes.
        $written = $this->rows->written(
            $credential,
            sprintf('%s = :codes, %s = :usedAt', $column, $this->rows->column($credential, 'lastUsedAt')),
            sprintf('CAST(%s AS TEXT) = :read', $column),
            [
                'codes' => $used->backupCodes,
                'usedAt' => $used->lastUsedAt,
                'read' => $credential->backupCodes,
            ],
            [
                'codes' => BackupCodesType::NAME,
                'usedAt' => Types::DATETIME_IMMUTABLE,
                'read' => BackupCodesType::NAME,
            ],
        );
        if ($written) {
            $credential->spendBackupCode($index, $now);
        }

        return $written;
    }
}
