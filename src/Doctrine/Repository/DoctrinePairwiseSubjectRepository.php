<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use Doctrine\DBAL\Types\Types;
use App\Doctrine\EntityManagers;
use App\Doctrine\Type\SectorIdentifierType;
use App\Doctrine\Type\SubjectType;
use Beffroi\Core\Domain\Identity\Subject;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Claims\PairwiseSubject;
use Beffroi\Oidc\Domain\Claims\SectorIdentifier;
use Beffroi\Oidc\Port\PairwiseSubjectRepositoryInterface;
use function sprintf;

/**
 * @extends DoctrineRepository<PairwiseSubject>
 */
final readonly class DoctrinePairwiseSubjectRepository extends DoctrineRepository implements PairwiseSubjectRepositoryInterface
{
    public function __construct(EntityManagers $managers)
    {
        parent::__construct($managers, PairwiseSubject::class);
    }

    public function findUser(Tenant $tenant, SectorIdentifier $sector, Subject $subject): ?User
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'sector' => $sector,
            'subject' => $subject,
        ])?->user;
    }

    /**
     * Not through the unit of work, whose flush would fail where two workers race into the unique index.
     */
    public function record(PairwiseSubject $assigned): void
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(PairwiseSubject::class);
        $manager->getConnection()->executeStatement(
            sprintf(
                'INSERT INTO %s (%s, %s, %s, %s, %s, %s) VALUES (:id, :tenant, :user, :sector, :subject, :assignedAt) ON CONFLICT DO NOTHING',
                $metadata->getTableName(),
                $metadata->getColumnName('id'),
                $metadata->getSingleAssociationJoinColumnName('tenant'),
                $metadata->getSingleAssociationJoinColumnName('user'),
                $metadata->getColumnName('sector'),
                $metadata->getColumnName('subject'),
                $metadata->getColumnName('assignedAt'),
            ),
            [
                'id' => $assigned->id,
                'tenant' => $assigned->tenant->id,
                'user' => $assigned->user->id,
                'sector' => $assigned->sector,
                'subject' => $assigned->subject,
                'assignedAt' => $assigned->assignedAt,
            ],
            [
                'id' => 'uuid',
                'tenant' => 'uuid',
                'user' => 'uuid',
                'sector' => SectorIdentifierType::NAME,
                'subject' => SubjectType::NAME,
                'assignedAt' => Types::DATETIME_IMMUTABLE,
            ],
        );
    }
}
