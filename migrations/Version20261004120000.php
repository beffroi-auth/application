<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;
use function sprintf;

final class Version20261004120000 extends SealingMigration
{
    private const string TABLE = 'beffroi_client';

    /**
     * A sealed column takes no default: `BYTEA DEFAULT '{}'` would hold two bytes that no key opens.
     *
     * @var array<string, array{string, string}> column, then its sealed declaration and its cleartext one
     */
    private const array COLUMNS = [
        'metadata' => ['BYTEA NOT NULL', 'JSONB NOT NULL'],
        'assigned_metadata' => ['BYTEA NOT NULL', "JSONB DEFAULT '{}' NOT NULL"],
        'secret' => ['BYTEA DEFAULT NULL', 'JSONB DEFAULT NULL'],
    ];

    /**
     * @return list<string>
     */
    protected function tablesToSeal(): array
    {
        return [self::TABLE];
    }

    public function getDescription(): string
    {
        return 'The registered metadata, the assigned metadata and the secret of a client, sealed (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::COLUMNS as $column => [$sealed]) {
            $this->addSql(sprintf('ALTER TABLE %s DROP %s', self::TABLE, $column));
            $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', self::TABLE, $column, $sealed));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::COLUMNS as $column => [, $cleartext]) {
            $this->addSql(sprintf('ALTER TABLE %s DROP %s', self::TABLE, $column));
            $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', self::TABLE, $column, $cleartext));
        }
    }
}
