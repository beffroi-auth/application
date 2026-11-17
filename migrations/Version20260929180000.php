<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The default, sixty-four zeroes, is a digest no value hashes to: the clients registered earlier hold no token.
 */
final class Version20260929180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The token a registered client reads, replaces and deletes itself with.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql("ALTER TABLE beffroi_client ADD registration_access_token CHAR(64) DEFAULT '0000000000000000000000000000000000000000000000000000000000000000' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_client DROP registration_access_token');
    }
}
