<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Saml\Domain\Metadata\AcceptedMetadata;
use Beffroi\Saml\Port\AcceptedMetadataRepositoryInterface;
use Beffroi\Saml\Port\CanForgetAcceptedMetadataInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use function sprintf;

/**
 * spec/saml.md S-6, S-8, S-42
 *
 * @extends DoctrineRepository<AcceptedMetadata>
 */
final readonly class DoctrineAcceptedMetadataRepository extends DoctrineRepository implements
    AcceptedMetadataRepositoryInterface,
    CanForgetAcceptedMetadataInterface
{
    /**
     * @param class-string<AcceptedMetadata> $entity the entity of the role this instance serves
     */
    public function __construct(
        EntityManagers $managers,
        private string $entity,
    ) {
        parent::__construct($managers, $entity);
    }

    public function find(Tenant $tenant, string $entityId): ?AcceptedMetadata
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'entityId' => $entityId,
        ]);
    }

    /**
     * One INSERT through the connection, not the unit of work: two registrations racing into the unique index leave one
     * row and fail neither, where a violation in a flush would close the unit of work.
     *
     * @throws LogicException when the row is another role's
     */
    public function register(AcceptedMetadata $metadata): bool
    {
        $this->assertOwn($metadata);
        $manager = $this->manager();
        $class = $manager->getClassMetadata($this->entity);
        $tenant = $class->getSingleAssociationJoinColumnName('tenant');
        $entityId = $class->getColumnName('entityId');

        return $manager->getConnection()->executeStatement(
            sprintf(
                'INSERT INTO %s (%s, %s, %s, %s, %s, %s) VALUES (:id, :tenant, :entityId, :document, :validUntil, :acceptedAt) ON CONFLICT (%s, %s) DO NOTHING',
                $class->getTableName(),
                $class->getColumnName('id'),
                $tenant,
                $entityId,
                $class->getColumnName('document'),
                $class->getColumnName('validUntil'),
                $class->getColumnName('acceptedAt'),
                $tenant,
                $entityId,
            ),
            [
                'id' => $metadata->id,
                'tenant' => $metadata->tenant->id,
                'entityId' => $metadata->entityId,
                'document' => $metadata->document,
                'validUntil' => $metadata->validUntil,
                'acceptedAt' => $metadata->acceptedAt,
            ],
            [
                'id' => self::type($class, 'id'),
                'tenant' => 'uuid',
                'entityId' => self::type($class, 'entityId'),
                'document' => self::type($class, 'document'),
                'validUntil' => self::type($class, 'validUntil'),
                'acceptedAt' => self::type($class, 'acceptedAt'),
            ],
        ) === 1;
    }

    public function save(AcceptedMetadata $metadata): void
    {
        $this->assertOwn($metadata);
        $this->store($metadata);
    }

    public function forget(AcceptedMetadata $metadata): void
    {
        $this->assertOwn($metadata);
        $this->discard($metadata);
    }

    /**
     * @throws LogicException
     */
    private function assertOwn(AcceptedMetadata $metadata): void
    {
        if (! $metadata instanceof $this->entity) {
            throw new LogicException(sprintf('The repository of %s is handed a %s, the row of another role.', $this->entity, $metadata::class));
        }
    }

    /**
     * @param ClassMetadata<AcceptedMetadata> $class
     */
    private static function type(ClassMetadata $class, string $field): string
    {
        return $class->getTypeOfField($field) ?? throw new LogicException(sprintf('The mapping of %s gives "%s" no type.', $class->getName(), $field));
    }
}
