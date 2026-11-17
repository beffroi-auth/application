<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use function array_values;
use function array_filter;
use Beffroi\Core\Domain\Listing\Period;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Identity\Subject;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Record\WhatATokenStillNeeds;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Domain\Listing\Listing;
use Beffroi\Core\Domain\Listing\Pagination;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\AuthenticationSessionRepositoryInterface;
use Beffroi\Core\Port\CanCountActiveSessionsInterface;
use Beffroi\Core\Port\CanFindUnendedSessionsInterface;
use Beffroi\Core\Port\CanPageActiveSessionsInterface;
use Beffroi\Core\Port\DeadRecordsInterface;
use Beffroi\Core\Port\SubjectsOfSessionsInterface;
use function is_array;
use SortDirection;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A DQL parameter that is an identifier names its type: the ORM would infer a string from a Uuid.
 *
 * @extends DoctrineRepository<AuthenticationSession>
 */
final readonly class DoctrineAuthenticationSessionRepository extends DoctrineRepository implements
    AuthenticationSessionRepositoryInterface,
    CanCountActiveSessionsInterface,
    CanFindUnendedSessionsInterface,
    CanPageActiveSessionsInterface,
    DeadRecordsInterface,
    SubjectsOfSessionsInterface
{
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
        private WhatATokenStillNeeds $stillNeeded,
    ) {
        parent::__construct($managers, AuthenticationSession::class);
    }

    /**
     * Kept past its expiry for as long as a token may still name it: AccessTokenInspector asks this row whether the
     * session was ended, and a row dropped earlier refuses a token that still holds.
     */
    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column(AuthenticationSession::class, 'expiresAt');

        return $this->sweep->from(
            AuthenticationSession::class,
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

    public function find(Uuid $id): ?AuthenticationSession
    {
        return $this->repository()->findOneBy([
            'id' => $id,
        ]);
    }

    /**
     * A scalar read: loading the session would open its sealed columns and those of its person.
     */
    public function of(Uuid $session): Subject
    {
        $row = $this->queryBuilder('s')
            ->select('u.subject')
            ->join('s.user', 'u')
            ->where('s.id = :session')
            ->setParameter('session', $session, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
        $subject = is_array($row) ? $row['subject'] ?? null : null;

        return $subject instanceof Subject ? $subject : throw new RuntimeException(sprintf('No session "%s" names a person.', $session->toRfc4122()));
    }

    /**
     * Paged in memory: the active sessions of one person are a handful.
     *
     * @return Listing<AuthenticationSession>
     */
    public function pageOfActiveSessions(User $user, DateTimeImmutable $now, Pagination $pagination, ?Period $period = null): Listing
    {
        return Listing::of(
            array_values(array_filter(
                $this->findActiveByUser($user, $now),
                static fn (AuthenticationSession $session): bool => $period?->holds($session->establishedAt) !== false,
            )),
            $pagination,
        );
    }

    public function findActiveByUser(User $user, DateTimeImmutable $now): array
    {
        /** @var list<AuthenticationSession> */
        return $this->unendedOf($user)->andWhere('s.expiresAt > :now')->setParameter('now', $now, Types::DATETIME_IMMUTABLE)->getQuery()->getResult();
    }

    public function countActiveSessionsIn(Tenant $tenant, DateTimeImmutable $now): int
    {
        return (int) $this->queryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.tenant = :tenant')
            ->andWhere('s.endedAt IS NULL')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUnendedByUser(User $user): array
    {
        /** @var list<AuthenticationSession> */
        return $this->unendedOf($user)->getQuery()->getResult();
    }

    private function unendedOf(User $user): QueryBuilder
    {
        return $this->queryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.endedAt IS NULL')
            ->orderBy('s.establishedAt', SortDirection::Ascending)
            ->addOrderBy('s.id', SortDirection::Ascending)
            ->setParameter('user', $user->id, UuidType::NAME);
    }

    public function save(AuthenticationSession $session): void
    {
        $this->store($session);
    }
}
