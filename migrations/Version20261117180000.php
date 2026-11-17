<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The tickets that let a person enrol an authenticator app in place of the one they hold (decision 0026).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_totp_ticket (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, secret JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, spent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_57ED55C29033212A ON beffroi_totp_ticket (tenant_id)');
        $this->addSql('CREATE INDEX beffroi_totp_ticket_expiry ON beffroi_totp_ticket (tenant_id, expires_at)');
        $this->addSql('CREATE INDEX beffroi_totp_ticket_user ON beffroi_totp_ticket (user_id)');
        $this->addSql('ALTER TABLE beffroi_totp_ticket ADD CONSTRAINT FK_57ED55C29033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_totp_ticket ADD CONSTRAINT FK_57ED55C2A76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_totp_ticket');
    }
}
