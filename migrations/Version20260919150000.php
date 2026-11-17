<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The passkey credentials of the identity model.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_passkey_credential (created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, name VARCHAR(255) NOT NULL, credential_id TEXT NOT NULL, public_key TEXT NOT NULL, sign_count BIGINT NOT NULL, transports JSONB NOT NULL, backup_eligible BOOLEAN NOT NULL, backed_up BOOLEAN NOT NULL, aaguid UUID NOT NULL, user_handle VARCHAR(86) NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BEA4109D9033212A ON beffroi_passkey_credential (tenant_id)');
        $this->addSql('CREATE INDEX IDX_BEA4109DA76ED395 ON beffroi_passkey_credential (user_id)');
        $this->addSql('CREATE INDEX beffroi_passkey_credential_user_handle ON beffroi_passkey_credential (tenant_id, user_handle)');
        $this->addSql('CREATE UNIQUE INDEX beffroi_passkey_credential_id ON beffroi_passkey_credential (tenant_id, credential_id)');
        $this->addSql('ALTER TABLE beffroi_passkey_credential ADD CONSTRAINT FK_BEA4109D9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_passkey_credential ADD CONSTRAINT FK_BEA4109DA76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_passkey_credential DROP CONSTRAINT FK_BEA4109D9033212A');
        $this->addSql('ALTER TABLE beffroi_passkey_credential DROP CONSTRAINT FK_BEA4109DA76ED395');
        $this->addSql('DROP TABLE beffroi_passkey_credential');
    }
}
