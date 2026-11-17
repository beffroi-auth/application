<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The archive a person asks for of everything the provider holds about them (issue #453).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_personal_archive (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, asked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, built_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, content BYTEA DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9FE6FB219033212A ON beffroi_personal_archive (tenant_id)');
        $this->addSql('CREATE INDEX IDX_9FE6FB21A76ED395 ON beffroi_personal_archive (user_id)');
        $this->addSql('CREATE INDEX beffroi_personal_archive_person ON beffroi_personal_archive (user_id, asked_at)');
        $this->addSql('CREATE INDEX beffroi_personal_archive_expiry ON beffroi_personal_archive (tenant_id, expires_at)');
        $this->addSql('ALTER TABLE beffroi_personal_archive ADD CONSTRAINT FK_9FE6FB219033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beffroi_personal_archive ADD CONSTRAINT FK_9FE6FB21A76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_personal_archive');
    }
}
