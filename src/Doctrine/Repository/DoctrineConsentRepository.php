<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use function array_filter;
use Beffroi\Core\Domain\Listing\Period;
use App\Doctrine\EntityManagers;
use App\Doctrine\Listener\ClientResolver;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Core\Domain\Listing\Listing;
use Beffroi\Core\Domain\Listing\Pagination;
use Beffroi\Oidc\Port\ActiveConsentsInterface;
use Beffroi\Oidc\Port\CanPageActiveConsentsInterface;
use Beffroi\Oidc\Port\ConsentRepositoryInterface;
use function array_values;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends DoctrineRepository<Consent>
 */
final readonly class DoctrineConsentRepository extends DoctrineRepository implements
    ConsentRepositoryInterface,
    ActiveConsentsInterface,
    CanPageActiveConsentsInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, Consent::class);
    }

    public function findActive(User $user, Client $client): ?Consent
    {
        /** @var Consent|null $consent */
        $consent = $this->queryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.clientId = :clientId')
            ->andWhere('c.revokedAt IS NULL')
            ->orderBy('c.grantedAt', SortDirection::Descending)
            ->addOrderBy('c.id', SortDirection::Descending)
            ->setParameter('user', $user->id, UuidType::NAME)
            ->setParameter('clientId', $client->registration->clientId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $consent !== null && ClientResolver::isResolved($consent) ? $consent : null;
    }

    /**
     * @return Listing<Consent>
     */
    public function pageOfActiveConsents(User $user, Pagination $pagination, ?Period $period = null): Listing
    {
        return Listing::of(
            array_values(array_filter(
                $this->activeOf($user),
                static fn (Consent $consent): bool => $period?->holds($consent->grantedAt) !== false,
            )),
            $pagination,
        );
    }

    /**
     * @return list<Consent>
     */
    public function activeOf(User $user): array
    {
        /** @var list<Consent> $consents */
        $consents = $this->queryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.revokedAt IS NULL')
            ->orderBy('c.grantedAt', SortDirection::Descending)
            ->addOrderBy('c.id', SortDirection::Descending)
            ->setParameter('user', $user->id, UuidType::NAME)
            ->getQuery()
            ->getResult();
        $found = [];
        foreach ($consents as $consent) {
            if (ClientResolver::isResolved($consent)) {
                $found[$consent->clientId] ??= $consent;
            }
        }

        return array_values($found);
    }

    public function save(Consent $consent): void
    {
        $this->store($consent);
    }
}
