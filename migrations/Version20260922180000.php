<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The pushed authorization requests.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_pushed_authorization_request (request_uri_hash CHAR(64) NOT NULL, client_id VARCHAR(255) NOT NULL, parameters JSONB NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (request_uri_hash))');
        $this->addSql('CREATE INDEX IDX_825B6A529033212A ON beffroi_pushed_authorization_request (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_pushed_authorization_request ADD CONSTRAINT FK_825B6A529033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_pushed_authorization_request');
    }
}
