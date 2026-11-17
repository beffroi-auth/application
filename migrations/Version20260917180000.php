<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The opaque access tokens of the OpenID Connect module.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_access_token (grant_id UUID NOT NULL, client_id VARCHAR(255) NOT NULL, scopes TEXT NOT NULL, claims JSONB DEFAULT NULL, authenticated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, amr TEXT NOT NULL, acr VARCHAR(255) NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash CHAR(64) NOT NULL, tenant_id UUID NOT NULL, session_id UUID NOT NULL, PRIMARY KEY (token_hash))');
        $this->addSql('CREATE INDEX beffroi_access_token_grant ON beffroi_access_token (grant_id)');
        $this->addSql('CREATE INDEX beffroi_access_token_expiry ON beffroi_access_token (expires_at)');
        $this->addSql('CREATE INDEX IDX_D0907679033212A ON beffroi_access_token (tenant_id)');
        $this->addSql('CREATE INDEX IDX_D090767613FECDF ON beffroi_access_token (session_id)');
        $this->addSql('ALTER TABLE beffroi_access_token ADD CONSTRAINT FK_D0907679033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_access_token ADD CONSTRAINT FK_D090767613FECDF FOREIGN KEY (session_id) REFERENCES beffroi_authentication_session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_access_token DROP CONSTRAINT FK_D0907679033212A');
        $this->addSql('ALTER TABLE beffroi_access_token DROP CONSTRAINT FK_D090767613FECDF');
        $this->addSql('DROP TABLE beffroi_access_token');
    }
}
