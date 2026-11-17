<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The opaque access tokens a client holds for itself.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_client_access_token (grant_id UUID NOT NULL, client_id VARCHAR(255) NOT NULL, scopes TEXT NOT NULL, resources TEXT DEFAULT NULL, cnf JSONB DEFAULT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash CHAR(64) NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (token_hash))');
        $this->addSql('CREATE INDEX beffroi_client_access_token_grant ON beffroi_client_access_token (grant_id)');
        $this->addSql('CREATE INDEX beffroi_client_access_token_expiry ON beffroi_client_access_token (expires_at)');
        $this->addSql('CREATE INDEX IDX_8FC157D59033212A ON beffroi_client_access_token (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_client_access_token ADD CONSTRAINT FK_8FC157D59033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_client_access_token DROP CONSTRAINT FK_8FC157D59033212A');
        $this->addSql('DROP TABLE beffroi_client_access_token');
    }
}
