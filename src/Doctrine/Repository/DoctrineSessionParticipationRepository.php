<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Saml\Domain\SingleLogout\SessionParticipation;
use Beffroi\Saml\Port\SessionParticipationRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use SortDirection;
use function sprintf;
use Symfony\Component\Uid\Uuid;

/**
 * beffroi/saml S-26
 *
 * @extends DoctrineRepository<SessionParticipation>
 */
final readonly class DoctrineSessionParticipationRepository extends DoctrineRepository implements SessionParticipationRepositoryInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, SessionParticipation::class);
    }

    /**
     * Not through the unit of work, whose flush would fail where two writes race into the unique index.
     */
    public function record(SessionParticipation $participation): void
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(SessionParticipation::class);
        $manager->getConnection()->executeStatement(
            sprintf(
                'INSERT INTO %s (%s, %s, %s, %s, %s) VALUES (:id, :tenant, :session, :serviceProvider, :signedInAt) ON CONFLICT DO NOTHING',
                $metadata->getTableName(),
                $metadata->getColumnName('id'),
                $metadata->getSingleAssociationJoinColumnName('tenant'),
                $metadata->getSingleAssociationJoinColumnName('session'),
                $metadata->getColumnName('serviceProvider'),
                $metadata->getColumnName('signedInAt'),
            ),
            [
                'id' => $participation->id,
                'tenant' => $participation->tenant->id,
                'session' => $participation->session->id,
                'serviceProvider' => $participation->serviceProvider,
                'signedInAt' => $participation->signedInAt,
            ],
            [
                'id' => 'uuid',
                'tenant' => 'uuid',
                'session' => 'uuid',
                'serviceProvider' => Types::STRING,
                'signedInAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }

    public function ofSession(Uuid $sessionId): array
    {
        /** @var list<SessionParticipation> */
        return $this->queryBuilder('participation')
            ->where('IDENTITY(participation.session) = :session')
            ->setParameter('session', $sessionId, 'uuid')
            ->orderBy('participation.signedInAt', SortDirection::Ascending)
            ->addOrderBy('participation.id', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
