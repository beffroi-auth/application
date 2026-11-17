<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The opaque access tokens whose subject was named by what the client presented.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_subject_access_token (sub VARCHAR(255) NOT NULL, session_id UUID DEFAULT NULL, authentication JSONB DEFAULT NULL, act JSONB DEFAULT NULL, grant_id UUID NOT NULL, client_id VARCHAR(255) NOT NULL, scopes TEXT NOT NULL, resources TEXT DEFAULT NULL, cnf JSONB DEFAULT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash CHAR(64) NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (token_hash))');
        $this->addSql('CREATE INDEX beffroi_subject_access_token_grant ON beffroi_subject_access_token (grant_id)');
        $this->addSql('CREATE INDEX beffroi_subject_access_token_session ON beffroi_subject_access_token (session_id)');
        $this->addSql('CREATE INDEX beffroi_subject_access_token_expiry ON beffroi_subject_access_token (expires_at)');
        $this->addSql('CREATE INDEX IDX_4866A6EE9033212A ON beffroi_subject_access_token (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_subject_access_token ADD CONSTRAINT FK_4866A6EE9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_subject_access_token');
    }
}
