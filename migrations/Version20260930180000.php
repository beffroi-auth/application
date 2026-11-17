<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The identifier each sector of a tenant sees a user under (OIDC Core section 8.1).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_pairwise_subject (sector VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX beffroi_pairwise_subject_identifier ON beffroi_pairwise_subject (tenant_id, sector, subject)');
        $this->addSql('CREATE UNIQUE INDEX beffroi_pairwise_subject_user ON beffroi_pairwise_subject (tenant_id, sector, user_id)');
        $this->addSql('CREATE INDEX IDX_63634AAF9033212A ON beffroi_pairwise_subject (tenant_id)');
        $this->addSql('CREATE INDEX IDX_63634AAFA76ED395 ON beffroi_pairwise_subject (user_id)');
        $this->addSql('ALTER TABLE beffroi_pairwise_subject ADD CONSTRAINT FK_63634AAF9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_pairwise_subject ADD CONSTRAINT FK_63634AAFA76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_pairwise_subject');
    }
}
