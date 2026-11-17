<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The initial access tokens of a tenant, and what a registration policy writes on a client.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_initial_access_token (digest CHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, registrations_allowed INT NOT NULL, registrations_served INT NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX beffroi_initial_access_token_digest ON beffroi_initial_access_token (tenant_id, digest)');
        $this->addSql('CREATE INDEX IDX_B7ED5F559033212A ON beffroi_initial_access_token (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_initial_access_token ADD CONSTRAINT FK_B7ED5F559033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql("ALTER TABLE beffroi_client ADD assigned_metadata JSONB DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_client DROP assigned_metadata');
        $this->addSql('DROP TABLE beffroi_initial_access_token');
    }
}
