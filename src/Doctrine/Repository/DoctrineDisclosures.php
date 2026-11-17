<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Oidc\Domain\Disclosure\Disclosure;
use Beffroi\Oidc\Port\DisclosuresInterface;
use Beffroi\Oidc\Port\DisclosuresOfAPersonInterface;
use function count;
use DateTimeImmutable;
use SortDirection;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<Disclosure>
 */
final readonly class DoctrineDisclosures extends DoctrineRepository implements DisclosuresInterface, DisclosuresOfAPersonInterface
{
    public const int FORGOTTEN_AT_ONCE = 1000;

    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, Disclosure::class);
    }

    public function record(Disclosure $disclosure): void
    {
        $this->store($this->namingTheRowOfItsTenant($disclosure));
    }

    public function forgetBefore(Tenant $tenant, DateTimeImmutable $moment): int
    {
        /** @var list<Disclosure> $past */
        $past = $this->queryBuilder('d')
            ->where('d.tenant = :tenant')
            ->andWhere('d.handedOverAt < :moment')
            ->orderBy('d.handedOverAt', SortDirection::Ascending)
            ->setMaxResults(self::FORGOTTEN_AT_ONCE)
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->setParameter('moment', $moment)
            ->getQuery()
            ->getResult();
        if ($past === []) {
            return 0;
        }
        $manager = $this->manager();
        foreach ($past as $disclosure) {
            $manager->remove($disclosure);
        }
        $manager->flush();

        return count($past);
    }

    public function everyDisclosureTo(User $user, int $howMany, ?Uuid $after = null): array
    {
        if ($howMany < 1) {
            return [];
        }
        $query = $this->queryBuilder('d')
            ->where('d.tenant = :tenant')
            ->andWhere('d.subject = :subject')
            ->orderBy('d.id', SortDirection::Ascending)
            ->setMaxResults($howMany)
            ->setParameter('tenant', $user->tenant->id, UuidType::NAME)
            ->setParameter('subject', $user->subject->value);
        if ($after !== null) {
            $query->andWhere('d.id > :after')->setParameter('after', $after, UuidType::NAME);
        }

        /** @var list<Disclosure> $page */
        $page = $query->getQuery()->getResult();

        return $page;
    }

    /**
     * A disclosure comes back from a queue, with a copy of its tenant the manager does not hold.
     */
    private function namingTheRowOfItsTenant(Disclosure $disclosure): Disclosure
    {
        $manager = $this->manager();
        if ($manager->contains($disclosure->tenant)) {
            return $disclosure;
        }
        $tenant = $manager->getReference(Tenant::class, $disclosure->tenant->id);
        if (! $tenant instanceof Tenant) {
            throw new RuntimeException(sprintf('The tenant "%s" of what a client was given has no row.', $disclosure->tenant->slug));
        }

        return new Disclosure(
            $disclosure->id,
            $tenant,
            $disclosure->subject,
            $disclosure->clientId,
            $disclosure->act,
            $disclosure->handedOverAt,
            $disclosure->scopes,
            $disclosure->claims,
            $disclosure->details,
            $disclosure->tokenShape,
        );
    }
}
