<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The journal of the acts of the provider (issue #454).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_journal (id UUID NOT NULL, tenant_id UUID NOT NULL, act VARCHAR(64) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, context BYTEA NOT NULL, subject VARCHAR(255) DEFAULT NULL, client VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FAC380D39033212A ON beffroi_journal (tenant_id)');
        $this->addSql('CREATE INDEX beffroi_journal_person ON beffroi_journal (tenant_id, subject, act, id)');
        $this->addSql('CREATE INDEX beffroi_journal_kept_until ON beffroi_journal (tenant_id, occurred_at)');
        $this->addSql('ALTER TABLE beffroi_journal ADD CONSTRAINT FK_FAC380D39033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_journal');
    }
}
