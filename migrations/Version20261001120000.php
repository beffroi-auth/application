<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The wrapped data key of a tenant and of a family of columns (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_data_key (id BYTEA NOT NULL, scope VARCHAR(191) NOT NULL, key_material BYTEA NOT NULL, master_key_id VARCHAR(255) NOT NULL, client VARCHAR(64) NOT NULL, binding BYTEA NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX beffroi_data_key_scope_idx ON beffroi_data_key (scope, id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_data_key');
    }
}
