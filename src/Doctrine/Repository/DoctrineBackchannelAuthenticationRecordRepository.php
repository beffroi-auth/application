<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use function array_filter;
use function array_values;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use App\Doctrine\EntityManagers;
use App\Doctrine\Listener\ClientResolver;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Record\WhatATokenStillNeeds;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\DeadRecordsInterface;
use Beffroi\Oidc\Domain\Ciba\BackchannelAuthenticationRecord;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Oidc\Port\BackchannelAuthenticationRecordRepositoryInterface;
use Beffroi\Oidc\Port\CanRevokeGrantsByConsentInterface;
use Beffroi\Oidc\Port\RecordedGrantsInterface;
use SortDirection;
use Symfony\Component\Uid\Uuid;
use Symfony\Bridge\Doctrine\Types\UuidType;
use function is_string;
use function sprintf;

/**
 * @extends DoctrineRepository<BackchannelAuthenticationRecord>
 */
final readonly class DoctrineBackchannelAuthenticationRecordRepository extends DoctrineRepository implements
    BackchannelAuthenticationRecordRepositoryInterface,
    CanRevokeGrantsByConsentInterface,
    DeadRecordsInterface,
    RecordedGrantsInterface
{
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
        private WhatATokenStillNeeds $stillNeeded,
    ) {
        parent::__construct($managers, BackchannelAuthenticationRecord::class);
    }

    /**
     * Kept for as long as a token may still need it: the record is the grant AccessTokenInspector checks.
     */
    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column(BackchannelAuthenticationRecord::class, 'expiresAt');

        return $this->sweep->from(
            BackchannelAuthenticationRecord::class,
            $tenant,
            sprintf('%s <= :dead', $expiry),
            $expiry,
            [
                'dead' => $this->stillNeeded->deadBefore($now),
            ],
            [
                'dead' => Types::DATETIME_IMMUTABLE,
            ],
            $limit,
        );
    }

    public function findByAuthRequestIdHash(string $authRequestIdHash): ?BackchannelAuthenticationRecord
    {
        $record = $this->repository()->findOneBy([
            'authRequestIdHash' => $authRequestIdHash,
        ]);

        return $record !== null && ClientResolver::isResolved($record) ? $record : null;
    }

    public function awaitingAnAnswerBy(User $user, DateTimeImmutable $now): array
    {
        /** @var list<BackchannelAuthenticationRecord> $records */
        $records = $this->queryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.expiresAt > :now')
            ->andWhere('r.approvedAt IS NULL')
            ->andWhere('r.deniedAt IS NULL')
            ->andWhere('r.usedAt IS NULL')
            ->setParameter('user', $user->id, UuidType::NAME)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->orderBy('r.issuedAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($records, ClientResolver::isResolved(...)));
    }

    public function findByGrantId(Uuid $grantId): ?BackchannelAuthenticationRecord
    {
        $record = $this->repository()->findOneBy([
            'grantId' => $grantId,
        ]);

        return $record !== null && ClientResolver::isResolved($record) ? $record : null;
    }

    /**
     * A request still waiting is revoked too: it names its user before anybody answers it.
     *
     * @return list<BackchannelAuthenticationRecord>
     */
    public function revokeByConsent(Consent $consent, DateTimeImmutable $now): array
    {
        /** @var list<BackchannelAuthenticationRecord> $records */
        $records = $this->queryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.clientId = :clientId')
            ->andWhere('r.revokedAt IS NULL')
            ->orderBy('r.issuedAt', SortDirection::Ascending)
            ->addOrderBy('r.authRequestIdHash', SortDirection::Ascending)
            ->setParameter('user', $consent->user->id, UuidType::NAME)
            ->setParameter('clientId', $consent->clientId)
            ->getQuery()
            ->getResult();
        $revoked = array_values(array_filter($records, ClientResolver::isResolved(...)));
        foreach ($revoked as $record) {
            $record->revoke($now);
        }
        $this->storeAll($revoked);

        return $revoked;
    }

    /**
     * One UPDATE ... WHERE used_at IS NULL through the connection: the affected row count says who claimed it.
     */
    public function consume(BackchannelAuthenticationRecord $record, DateTimeImmutable $now): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(BackchannelAuthenticationRecord::class);
        $claimed =
            $manager->getConnection()->executeStatement(
                sprintf(
                    'UPDATE %s SET %s = :now WHERE %s = :authRequestIdHash AND %s IS NULL',
                    $metadata->getTableName(),
                    $metadata->getColumnName('usedAt'),
                    $metadata->getColumnName('authRequestIdHash'),
                    $metadata->getColumnName('usedAt'),
                ),
                [
                    'now' => $now,
                    'authRequestIdHash' => $record->authRequestIdHash,
                ],
                [
                    'now' => Types::DATETIME_IMMUTABLE,
                    'authRequestIdHash' => ParameterType::STRING,
                ],
            ) === 1;
        if ($claimed) {
            $record->consume($now);

            return true;
        }
        $usedAt = $manager->getConnection()->fetchOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :authRequestIdHash',
                $metadata->getColumnName('usedAt'),
                $metadata->getTableName(),
                $metadata->getColumnName('authRequestIdHash'),
            ),
            [
                'authRequestIdHash' => $record->authRequestIdHash,
            ],
            [
                'authRequestIdHash' => ParameterType::STRING,
            ],
        );
        if (is_string($usedAt)) {
            $record->recordConsumption(new DateTimeImmutable($usedAt));
        }

        return false;
    }

    public function save(BackchannelAuthenticationRecord $record): void
    {
        $this->store($record);
    }
}
