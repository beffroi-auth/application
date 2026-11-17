<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use Beffroi\Core\Application\Group\MembershipAlreadyHeldException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Group\GroupMembership;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\GroupMembershipRepositoryInterface;
use Beffroi\Core\Port\GroupsOfAPersonInterface;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends DoctrineRepository<GroupMembership>
 */
final readonly class DoctrineGroupMembershipRepository extends DoctrineRepository implements GroupMembershipRepositoryInterface, GroupsOfAPersonInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, GroupMembership::class);
    }

    public function of(User $user): array
    {
        /** @var list<GroupMembership> $memberships */
        $memberships = $this->queryBuilder('m')
            ->join('m.group', 'g')
            ->where('m.user = :user')
            ->orderBy('g.name', SortDirection::Ascending)
            ->setParameter('user', $user->id, UuidType::NAME)
            ->getQuery()
            ->getResult();

        return $memberships;
    }

    /**
     * beffroi/saml S-56
     *
     * @throws MembershipAlreadyHeldException
     */
    public function save(GroupMembership $membership): void
    {
        try {
            $this->store($membership);
        } catch (UniqueConstraintViolationException $exception) {
            throw MembershipAlreadyHeldException::writtenMeanwhile($exception);
        }
    }

    public function remove(GroupMembership $membership): void
    {
        $this->discard($membership);
    }
}
