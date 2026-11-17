<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The backchannel authentication requests.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_backchannel_authentication_request (auth_request_id_hash CHAR(64) NOT NULL, client_id VARCHAR(255) NOT NULL, scopes TEXT NOT NULL, resources TEXT DEFAULT NULL, binding_message VARCHAR(255) DEFAULT NULL, sealed_auth_request_id TEXT DEFAULT NULL, sealed_notification_token TEXT DEFAULT NULL, requested_from_received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, requested_from_ip_address VARCHAR(45) DEFAULT NULL, requested_from_user_agent VARCHAR(1024) DEFAULT NULL, requested_from_location JSONB DEFAULT NULL, requested_from_device JSONB DEFAULT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, poll_interval INT NOT NULL, last_polled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, denied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, authentication JSONB DEFAULT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tenant_id UUID NOT NULL, user_id UUID DEFAULT NULL, session_id UUID DEFAULT NULL, PRIMARY KEY (auth_request_id_hash))');
        $this->addSql('CREATE INDEX beffroi_backchannel_authentication_expiry ON beffroi_backchannel_authentication_request (expires_at)');
        $this->addSql('CREATE INDEX IDX_26F338269033212A ON beffroi_backchannel_authentication_request (tenant_id)');
        $this->addSql('CREATE INDEX IDX_26F33826A76ED395 ON beffroi_backchannel_authentication_request (user_id)');
        $this->addSql('CREATE INDEX IDX_26F33826613FECDF ON beffroi_backchannel_authentication_request (session_id)');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD CONSTRAINT FK_26F338269033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD CONSTRAINT FK_26F33826A76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD CONSTRAINT FK_26F33826613FECDF FOREIGN KEY (session_id) REFERENCES beffroi_authentication_session (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP CONSTRAINT FK_26F33826613FECDF');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP CONSTRAINT FK_26F33826A76ED395');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP CONSTRAINT FK_26F338269033212A');
        $this->addSql('DROP TABLE beffroi_backchannel_authentication_request');
    }
}
