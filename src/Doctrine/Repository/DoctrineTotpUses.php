<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use App\Doctrine\Type\BackupCodesType;
use Beffroi\Core\Domain\Credential\TotpCredential;
use Beffroi\Core\Port\TotpUsesInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * One UPDATE through the connection, conditioned on what the row still holds: the affected row count says who wrote.
 * A copy of the credential takes the use first, so that its rules refuse the use before the row is written.
 *
 * @extends DoctrineRepository<TotpCredential>
 */
final readonly class DoctrineTotpUses extends DoctrineRepository implements TotpUsesInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, TotpCredential::class);
    }

    public function acceptStep(TotpCredential $credential, int $step, DateTimeImmutable $now): bool
    {
        if (! $credential->acceptsStep($step)) {
            return false;
        }
        $used = clone $credential;
        $used->recordUse($step, $now);
        $column = $this->column('lastAcceptedStep');
        $written = $this->written(
            $credential,
            sprintf('%s = :step, %s = :usedAt', $column, $this->column('lastUsedAt')),
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
        $column = $this->column('backupCodes');
        // The column keeps the text it was given, and the type writes one text for one list of codes.
        $written = $this->written(
            $credential,
            sprintf('%s = :codes, %s = :usedAt', $column, $this->column('lastUsedAt')),
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

    /**
     * The credential leaves the unit of work whatever the count, so that no later flush writes over the row.
     *
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $types
     */
    private function written(TotpCredential $credential, string $assignments, string $condition, array $parameters, array $types): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(TotpCredential::class);
        $written =
            $manager->getConnection()->executeStatement(
                sprintf('UPDATE %s SET %s WHERE %s = :id AND %s', $metadata->getTableName(), $assignments, $metadata->getColumnName('id'), $condition),
                [
                    ...$parameters,
                    'id' => $credential->id,
                ],
                [
                    ...$types,
                    'id' => UuidType::NAME,
                ],
            ) === 1;
        $manager->detach($credential);

        return $written;
    }

    private function column(string $field): string
    {
        return $this->manager()->getClassMetadata(TotpCredential::class)->getColumnName($field);
    }
}
