<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Who did an act to a person when it was somebody else (decision 0038): the administrator who read them through the
 * administration API of the tenant, by the subject of their own account, in the clear as the subject of the person is.
 *
 * Nullable, so that every act the journal already holds says what it was: done by the person, or by nobody this
 * provider knows.
 */
final class Version20261104140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The author of an act somebody did to a person (decision 0038).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_journal ADD author VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_journal DROP author');
    }
}
