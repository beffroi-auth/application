<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use App\Doctrine\EntityManagers;
use App\Doctrine\Listener\ClientResolver;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\DeadRecordsInterface;
use Beffroi\Oidc\Domain\PushedAuthorization\PushedAuthorizationRecord;
use Beffroi\Oidc\Port\PushedAuthorizationRecordRepositoryInterface;
use function is_string;
use function sprintf;

/**
 * @extends DoctrineRepository<PushedAuthorizationRecord>
 */
final readonly class DoctrinePushedAuthorizationRecordRepository extends DoctrineRepository implements
    DeadRecordsInterface,
    PushedAuthorizationRecordRepositoryInterface
{
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
    ) {
        parent::__construct($managers, PushedAuthorizationRecord::class);
    }

    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column(PushedAuthorizationRecord::class, 'expiresAt');

        return $this->sweep->from(
            PushedAuthorizationRecord::class,
            $tenant,
            sprintf('%s <= :now', $expiry),
            $expiry,
            [
                'now' => $now,
            ],
            [
                'now' => Types::DATETIME_IMMUTABLE,
            ],
            $limit,
        );
    }

    public function findByRequestUriHash(string $requestUriHash): ?PushedAuthorizationRecord
    {
        $record = $this->repository()->findOneBy([
            'requestUriHash' => $requestUriHash,
        ]);

        return $record !== null && ClientResolver::isResolved($record) ? $record : null;
    }

    /**
     * Through the connection, not the unit of work: of two concurrent requests, one only consumes the record.
     */
    public function consume(PushedAuthorizationRecord $record, DateTimeImmutable $now): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(PushedAuthorizationRecord::class);
        $claimed =
            $manager->getConnection()->executeStatement(
                sprintf(
                    'UPDATE %s SET %s = :now WHERE %s = :requestUriHash AND %s IS NULL',
                    $metadata->getTableName(),
                    $metadata->getColumnName('usedAt'),
                    $metadata->getColumnName('requestUriHash'),
                    $metadata->getColumnName('usedAt'),
                ),
                [
                    'now' => $now,
                    'requestUriHash' => $record->requestUriHash,
                ],
                [
                    'now' => Types::DATETIME_IMMUTABLE,
                    'requestUriHash' => ParameterType::STRING,
                ],
            ) === 1;
        if ($claimed) {
            $record->consume($now);

            return true;
        }
        $usedAt = $manager->getConnection()->fetchOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :requestUriHash',
                $metadata->getColumnName('usedAt'),
                $metadata->getTableName(),
                $metadata->getColumnName('requestUriHash'),
            ),
            [
                'requestUriHash' => $record->requestUriHash,
            ],
            [
                'requestUriHash' => ParameterType::STRING,
            ],
        );
        if (is_string($usedAt)) {
            $record->recordConsumption(new DateTimeImmutable($usedAt));
        }

        return false;
    }

    public function save(PushedAuthorizationRecord $record): void
    {
        $this->store($record);
    }
}
