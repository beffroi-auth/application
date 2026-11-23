<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Why a refusal refused and the method it refused (decision 0062), words this provider gives them, in the clear.
 *
 * Nullable, so that every act the journal already holds says what it was: one that said neither.
 */
final class Version20261124090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The reason of a refusal and the method it refused (decision 0062).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_journal ADD reason VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_journal ADD method VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_journal DROP reason');
        $this->addSql('ALTER TABLE beffroi_journal DROP method');
    }
}
