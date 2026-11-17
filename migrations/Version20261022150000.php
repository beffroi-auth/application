<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An index and not a constraint: two local persons may share an address.
 */
final class Version20261022150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The search of an address on its blind index within a tenant (issue #781).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE INDEX beffroi_user_email ON beffroi_user (tenant_id, email_index)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP INDEX beffroi_user_email');
    }
}
