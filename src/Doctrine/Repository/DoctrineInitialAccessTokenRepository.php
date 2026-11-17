<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Client\Registration\InitialAccessToken;
use Beffroi\Oidc\Port\InitialAccessTokenRepositoryInterface;
use Beffroi\Oidc\Port\IssuedInitialAccessTokensInterface;
use function is_numeric;
use function sprintf;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<InitialAccessToken>
 */
final readonly class DoctrineInitialAccessTokenRepository extends DoctrineRepository implements
    InitialAccessTokenRepositoryInterface,
    IssuedInitialAccessTokensInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, InitialAccessToken::class);
    }

    public function findByDigest(Tenant $tenant, string $digest): ?InitialAccessToken
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'digest' => $digest,
        ]);
    }

    /**
     * Through the connection, not the unit of work: concurrent requests never spend one registration twice.
     */
    public function spend(InitialAccessToken $token, DateTimeImmutable $now): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(InitialAccessToken::class);
        $served = $metadata->getColumnName('registrationsServed');
        $claimed =
            $manager->getConnection()->executeStatement(
                sprintf(
                    'UPDATE %s SET %s = %s + 1 WHERE %s = :digest AND %s < %s AND %s IS NULL AND %s > :now',
                    $metadata->getTableName(),
                    $served,
                    $served,
                    $metadata->getColumnName('digest'),
                    $served,
                    $metadata->getColumnName('registrationsAllowed'),
                    $metadata->getColumnName('revokedAt'),
                    $metadata->getColumnName('expiresAt'),
                ),
                [
                    'digest' => $token->digest,
                    'now' => $now,
                ],
                [
                    'digest' => ParameterType::STRING,
                    'now' => Types::DATETIME_IMMUTABLE,
                ],
            ) === 1;
        $token->recordRegistrationsServed($this->registrationsServed($token, $metadata->getTableName(), $served, $metadata->getColumnName('digest')));

        return $claimed;
    }

    public function find(Tenant $tenant, Uuid $id): ?InitialAccessToken
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'id' => $id,
        ]);
    }

    /**
     * @return list<InitialAccessToken>
     */
    public function issuedIn(Tenant $tenant): iterable
    {
        return $this->repository()->findBy([
            'tenant' => $tenant,
        ], [
            'issuedAt' => 'DESC',
        ]);
    }

    public function save(InitialAccessToken $token): void
    {
        $this->store($token);
    }

    private function registrationsServed(InitialAccessToken $token, string $table, string $served, string $digest): int
    {
        $count = $this->manager()
            ->getConnection()
            ->fetchOne(
                sprintf('SELECT %s FROM %s WHERE %s = :digest', $served, $table, $digest),
                [
                    'digest' => $token->digest,
                ],
                [
                    'digest' => ParameterType::STRING,
                ],
            );

        return is_numeric($count) ? (int) $count : $token->registrationsServed;
    }
}
