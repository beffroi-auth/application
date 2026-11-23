<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Credential\Credential;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * The use of a credential written by one UPDATE through the connection, conditioned on what its row still holds: the
 * affected row count says who wrote.
 */
final readonly class ConditionalCredentialWrite
{
    public function __construct(
        private EntityManagers $managers,
    ) {
    }

    public function column(Credential $credential, string $field): string
    {
        return $this->managers->forClass($credential::class)->getClassMetadata($credential::class)->getColumnName($field);
    }

    /**
     * The credential leaves the unit of work whatever the count, so that no later flush of the request writes over
     * the row.
     *
     * @param array<string, mixed>  $parameters
     * @param array<string, string> $types
     */
    public function written(Credential $credential, string $assignments, string $condition, array $parameters, array $types): bool
    {
        $manager = $this->managers->forClass($credential::class);
        $metadata = $manager->getClassMetadata($credential::class);
        $written =
            $manager->getConnection()->executeStatement(
                sprintf('UPDATE %s SET %s WHERE %s = :id AND %s', $metadata->getTableName(), $assignments, $metadata->getColumnName('id'), $condition),
                [
                    ...$parameters,
                    'id' => $credential->id,
                ],
                [
                    ...$types,
                    'id' => UuidType::NAME,
                ],
            ) === 1;
        $manager->detach($credential);

        return $written;
    }
}
