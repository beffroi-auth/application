<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'What a client was given of a person, at one act of the protocol (issue #590).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_disclosure (id UUID NOT NULL, tenant_id UUID NOT NULL, subject VARCHAR(255) NOT NULL, client_id VARCHAR(255) NOT NULL, act VARCHAR(64) NOT NULL, handed_over_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scopes BYTEA NOT NULL, claims BYTEA DEFAULT NULL, authorization_details BYTEA DEFAULT NULL, token_shape VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AB1CF2BF9033212A ON beffroi_disclosure (tenant_id)');
        $this->addSql('CREATE INDEX beffroi_disclosure_person ON beffroi_disclosure (tenant_id, subject, id)');
        $this->addSql('CREATE INDEX beffroi_disclosure_kept_until ON beffroi_disclosure (tenant_id, handed_over_at)');
        $this->addSql('ALTER TABLE beffroi_disclosure ADD CONSTRAINT FK_AB1CF2BF9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_disclosure');
    }
}
