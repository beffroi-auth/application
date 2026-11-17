<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The TOTP credentials of the identity model.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_totp_credential (created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, sealed_secret TEXT NOT NULL, last_accepted_step BIGINT NOT NULL, backup_codes JSON NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E3815CBD9033212A ON beffroi_totp_credential (tenant_id)');
        $this->addSql('CREATE INDEX IDX_E3815CBDA76ED395 ON beffroi_totp_credential (user_id)');
        $this->addSql('ALTER TABLE beffroi_totp_credential ADD CONSTRAINT FK_E3815CBD9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_totp_credential ADD CONSTRAINT FK_E3815CBDA76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_totp_credential DROP CONSTRAINT FK_E3815CBD9033212A');
        $this->addSql('ALTER TABLE beffroi_totp_credential DROP CONSTRAINT FK_E3815CBDA76ED395');
        $this->addSql('DROP TABLE beffroi_totp_credential');
    }
}
