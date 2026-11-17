<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The clients this provider registered itself.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_client (client_id VARCHAR(255) NOT NULL, metadata JSONB NOT NULL, secret JSONB DEFAULT NULL, client_id_issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, client_secret_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX beffroi_client_identifier ON beffroi_client (tenant_id, client_id)');
        $this->addSql('CREATE INDEX IDX_CD0352759033212A ON beffroi_client (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_client ADD CONSTRAINT FK_CD0352759033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_client');
    }
}
