<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use App\Doctrine\Listener\ClientResolver;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\DeadRecordsInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Oidc\Port\InteractionRepositoryInterface;
use function sprintf;

/**
 * @extends DoctrineRepository<Interaction>
 */
final readonly class DoctrineInteractionRepository extends DoctrineRepository implements DeadRecordsInterface, InteractionRepositoryInterface
{
    public function __construct(
        EntityManagers $managers,
        private BoundedDelete $sweep,
    ) {
        parent::__construct($managers, Interaction::class);
    }

    public function forgetWhatIsDead(Tenant $tenant, DateTimeImmutable $now, int $limit): int
    {
        $expiry = $this->sweep->column(Interaction::class, 'expiresAt');

        return $this->sweep->from(
            Interaction::class,
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

    public function find(string $id): ?Interaction
    {
        $interaction = $this->repository()->findOneBy([
            'id' => $id,
        ]);

        return $interaction !== null && ClientResolver::isResolved($interaction) ? $interaction : null;
    }

    public function save(Interaction $interaction): void
    {
        $this->store($interaction);
    }
}
