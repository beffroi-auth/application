<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'What an initial access token gives the clients it registers, and what a registered client was given (decision 0043).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_initial_access_token ADD granted JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_client ADD granted_metadata BYTEA DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_client DROP granted_metadata');
        $this->addSql('ALTER TABLE beffroi_initial_access_token DROP granted');
    }
}
