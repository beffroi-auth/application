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
use Beffroi\Core\Domain\Record\WhatATokenStillNeeds;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\DeadRecordsInterface;
use Beffroi\Oidc\Domain\Authorization\AuthorizationCode;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Oidc\Port\AuthorizationCodeRepositoryInterface;
use Beffroi\Oidc\Port\CanRevokeGrantsByConsentInterface;
use Beffroi\Oidc\Port\LoggedInClientsInterface;
use Beffroi\Oidc\Port\RecordedGrantsInterface;
use function is_string;
use SortDirection;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<AuthorizationCode>
 */
final readonly class DoctrineAuthorizationCodeRepository extends DoctrineRepository implements
    AuthorizationCodeRepositoryInterface,
    CanRevokeGrantsByConsentInterface,
    DeadRecordsInterface,
    LoggedInClientsInterface,
    RecordedGrantsInterface
{
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
        private WhatATokenStillNeeds $stillNeeded,
    ) {
        parent::__construct($managers, AuthorizationCode::class);
    }

    /**
     * Kept for as long as a token may still need it: the code row is the grant AccessTokenInspector checks.
     */
    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column(AuthorizationCode::class, 'expiresAt');

        return $this->sweep->from(
            AuthorizationCode::class,
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

    public function findByCodeHash(string $codeHash): ?AuthorizationCode
    {
        $code = $this->repository()->findOneBy([
            'codeHash' => $codeHash,
        ]);

        return $code !== null && ClientResolver::isResolved($code) ? $code : null;
    }

    public function findByGrantId(Uuid $grantId): ?AuthorizationCode
    {
        $code = $this->repository()->findOneBy([
            'grantId' => $grantId,
        ]);

        return $code !== null && ClientResolver::isResolved($code) ? $code : null;
    }

    /**
     * @return list<Client> each once, oldest grant first
     */
    public function ofSession(Uuid $session): array
    {
        $codes = $this->repository()->findBy([
            'session' => $session,
        ], [
            'issuedAt' => 'ASC',
            'codeHash' => 'ASC',
        ]);
        $clients = [];
        foreach ($codes as $code) {
            if ($code->isUsed() && ClientResolver::isResolved($code)) {
                $clients[$code->clientId] = $code->client;
            }
        }

        return array_values($clients);
    }

    /**
     * A code not yet exchanged is revoked too: nothing may come of it now.
     *
     * @return list<AuthorizationCode>
     */
    public function revokeByConsent(Consent $consent, DateTimeImmutable $now): array
    {
        $revoked = [];
        foreach ($this->ofConsent($consent) as $code) {
            if ($code->isRevoked()) {
                continue;
            }
            $code->revoke($now);
            $revoked[] = $code;
        }
        $this->storeAll($revoked);

        return $revoked;
    }

    /**
     * @return list<AuthorizationCode>
     */
    private function ofConsent(Consent $consent): array
    {
        /** @var list<AuthorizationCode> $codes */
        $codes = $this->queryBuilder('c')
            ->join('c.session', 's')
            ->where('s.user = :user')
            ->andWhere('c.clientId = :clientId')
            ->orderBy('c.issuedAt', SortDirection::Ascending)
            ->addOrderBy('c.codeHash', SortDirection::Ascending)
            ->setParameter('user', $consent->user->id, UuidType::NAME)
            ->setParameter('clientId', $consent->clientId)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($codes, ClientResolver::isResolved(...)));
    }

    /**
     * One UPDATE ... WHERE used_at IS NULL through the connection: the affected row count says who claimed it.
     */
    public function consume(AuthorizationCode $code, DateTimeImmutable $now): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(AuthorizationCode::class);
        $claimed =
            $manager->getConnection()->executeStatement(
                sprintf(
                    'UPDATE %s SET %s = :now WHERE %s = :codeHash AND %s IS NULL',
                    $metadata->getTableName(),
                    $metadata->getColumnName('usedAt'),
                    $metadata->getColumnName('codeHash'),
                    $metadata->getColumnName('usedAt'),
                ),
                [
                    'now' => $now,
                    'codeHash' => $code->codeHash,
                ],
                [
                    'now' => Types::DATETIME_IMMUTABLE,
                    'codeHash' => ParameterType::STRING,
                ],
            ) === 1;
        if ($claimed) {
            $code->consume($now);

            return true;
        }
        $usedAt = $manager->getConnection()->fetchOne(
            sprintf(
                'SELECT %s FROM %s WHERE %s = :codeHash',
                $metadata->getColumnName('usedAt'),
                $metadata->getTableName(),
                $metadata->getColumnName('codeHash'),
            ),
            [
                'codeHash' => $code->codeHash,
            ],
            [
                'codeHash' => ParameterType::STRING,
            ],
        );
        if (is_string($usedAt)) {
            $code->recordConsumption(new DateTimeImmutable($usedAt));
        }

        return false;
    }

    public function save(AuthorizationCode $code): void
    {
        $this->store($code);
    }
}
