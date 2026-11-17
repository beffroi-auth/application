<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use function array_values;
use function array_filter;
use Beffroi\Core\Domain\Listing\Period;
use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Identity\PushSubscription;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Listing\Listing;
use Beffroi\Core\Domain\Listing\Pagination;
use Beffroi\Core\Port\CanPageActiveSubscriptionsInterface;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @extends DoctrineRepository<PushSubscription>
 */
final readonly class DoctrinePushSubscriptionRepository extends DoctrineRepository implements
    CanPageActiveSubscriptionsInterface,
    PushSubscriptionRepositoryInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, PushSubscription::class);
    }

    /**
     * @return Listing<PushSubscription>
     */
    public function pageOfActiveSubscriptions(User $user, Pagination $pagination, ?Period $period = null): Listing
    {
        return Listing::of(
            array_values(array_filter(
                $this->findActiveByUser($user),
                static fn (PushSubscription $subscription): bool => $period?->holds($subscription->createdAt) !== false,
            )),
            $pagination,
        );
    }

    public function findActiveByUser(User $user): array
    {
        return $this->repository()->findBy([
            'user' => $user,
            'revokedAt' => null,
        ], [
            'createdAt' => 'ASC',
        ]);
    }

    public function findByEndpoint(User $user, string $endpoint): ?PushSubscription
    {
        return $this->repository()->findOneBy([
            'user' => $user,
            'endpointHash' => PushSubscription::digestOf($endpoint),
        ]);
    }

    public function find(Uuid $id): ?PushSubscription
    {
        return $this->repository()->find($id);
    }

    public function save(PushSubscription $subscription): void
    {
        $this->store($subscription);
    }
}
