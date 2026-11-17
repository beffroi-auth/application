<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Doctrine\DBAL\ParameterType;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * Through the connection and not the unit of work: hydrating a row to delete it would open its sealed columns.
 */
final readonly class BoundedDelete
{
    /**
     * Named so that no parameter of a caller's predicate can collide with it.
     */
    private const string TENANT = 'beffroiTenantOfTheSweep';

    public function __construct(
        private EntityManagers $managers,
    ) {
    }

    /**
     * @param class-string $class
     * @param string $where a predicate written in columns of that table
     * @param array<string, mixed> $parameters
     * @param array<string, string|ParameterType> $types
     */
    public function from(
        string $class,
        Tenant $tenant,
        string $where,
        string $oldestFirst,
        array $parameters,
        array $types,
        int $limit,
    ): int {
        $manager = $this->managers->forClass($class);
        $metadata = $manager->getClassMetadata($class);
        $key = $metadata->getSingleIdentifierColumnName();

        return (int) $manager->getConnection()->executeStatement(
            sprintf(
                'DELETE FROM %1$s WHERE %2$s IN (SELECT %2$s FROM %1$s WHERE %3$s = :%4$s AND (%5$s) ORDER BY %6$s ASC, %2$s ASC LIMIT %7$d)',
                $metadata->getTableName(),
                $key,
                $metadata->getSingleAssociationJoinColumnName('tenant'),
                self::TENANT,
                $where,
                $oldestFirst,
                $limit,
            ),
            [
                ...$parameters,
                self::TENANT => $tenant->id,
            ],
            [
                ...$types,
                self::TENANT => UuidType::NAME,
            ],
        );
    }

    /**
     * @param class-string $class
     */
    public function column(string $class, string $field): string
    {
        return $this->managers->forClass($class)->getClassMetadata($class)->getColumnName($field);
    }

    /**
     * @param class-string $class
     */
    public function joinColumn(string $class, string $association): string
    {
        return $this->managers->forClass($class)->getClassMetadata($class)->getSingleAssociationJoinColumnName($association);
    }

    /**
     * @param class-string $class
     */
    public function table(string $class): string
    {
        return $this->managers->forClass($class)->getClassMetadata($class)->getTableName();
    }
}
