<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use App\Doctrine\PersonalData\BlindIndexedColumns;
use App\Doctrine\PersonalData\TenantBlindIndexes;
use Beffroi\Core\Application\Identity\IdentityAlreadyLinkedException;
use Beffroi\Core\Domain\Identity\LinkedIdentity;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\LinkedIdentitiesOfAPersonInterface;
use Beffroi\Core\Port\LinkedIdentityRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends DoctrineRepository<LinkedIdentity>
 */
final readonly class DoctrineLinkedIdentityRepository extends DoctrineRepository implements
    LinkedIdentitiesOfAPersonInterface,
    LinkedIdentityRepositoryInterface
{
    public function __construct(
        EntityManagers $managers,
        private TenantBlindIndexes $indexes,
    ) {
        parent::__construct($managers, LinkedIdentity::class);
    }

    public function find(Tenant $tenant, string $provider, string $subject): ?LinkedIdentity
    {
        $providerColumn = BlindIndexedColumns::of(LinkedIdentity::class, 'provider');
        $subjectColumn = BlindIndexedColumns::of(LinkedIdentity::class, 'subject');

        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            $providerColumn->tag => $this->indexes->of($providerColumn)->of($provider),
            $subjectColumn->tag => $this->indexes->of($subjectColumn)->of($subject),
        ]);
    }

    public function of(User $user): array
    {
        /** @var list<LinkedIdentity> $identities */
        $identities = $this->queryBuilder('i')
            ->where('i.user = :user')
            ->orderBy('i.linkedAt', SortDirection::Descending)
            ->setParameter('user', $user->id, UuidType::NAME)
            ->getQuery()
            ->getResult();

        return $identities;
    }

    /**
     * beffroi/saml S-56
     *
     * @throws IdentityAlreadyLinkedException
     */
    public function save(LinkedIdentity $identity): void
    {
        try {
            $this->store($identity);
        } catch (UniqueConstraintViolationException $exception) {
            throw IdentityAlreadyLinkedException::linkedMeanwhile($exception);
        }
    }
}
