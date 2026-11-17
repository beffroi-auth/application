<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Saml\Domain\Replay\RememberedMessage;
use Beffroi\Saml\Port\ReplayStoreInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use function sprintf;

/**
 * beffroi/saml S-23, S-53, model.md M-11
 *
 * Through the connection: an insertion's count names the one worker that accepts a message, as no cache pool can.
 *
 * @extends DoctrineRepository<RememberedMessage>
 */
final readonly class DoctrineReplayStore extends DoctrineRepository implements ReplayStoreInterface
{
    public function __construct(
        EntityManagers $managers,
        private ClockInterface $clock,
    ) {
        parent::__construct($managers, RememberedMessage::class);
    }

    public function remember(Tenant $tenant, string $issuer, string $id, DateTimeImmutable $expiresAt): bool
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(RememberedMessage::class);
        $connection = $manager->getConnection();
        $table = $metadata->getTableName();
        $expiry = $metadata->getColumnName('expiresAt');
        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s <= :now', $table, $expiry),
            ['now' => $this->clock->now()],
            ['now' => Types::DATETIME_IMMUTABLE],
        );
        $written = $connection->executeStatement(
            sprintf(
                'INSERT INTO %s (%s, %s, %s) VALUES (:key, :tenant, :expiresAt) ON CONFLICT DO NOTHING',
                $table,
                $metadata->getColumnName('key'),
                $metadata->getSingleAssociationJoinColumnName('tenant'),
                $expiry,
            ),
            [
                'key' => RememberedMessage::keyOf($tenant, $issuer, $id),
                'tenant' => $tenant->id,
                'expiresAt' => $expiresAt,
            ],
            [
                'key' => Types::STRING,
                'tenant' => 'uuid',
                'expiresAt' => Types::DATETIME_IMMUTABLE,
            ],
        );

        return $written === 1;
    }
}
