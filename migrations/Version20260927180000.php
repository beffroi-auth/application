<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The browsers of a user this provider may push to.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_push_subscription (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, endpoint TEXT NOT NULL, endpoint_hash CHAR(64) NOT NULL, public_key VARCHAR(255) NOT NULL, auth_secret VARCHAR(255) NOT NULL, application_server_kid VARCHAR(255) NOT NULL, label VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_pushed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX beffroi_push_subscription_endpoint ON beffroi_push_subscription (user_id, endpoint_hash)');
        $this->addSql('CREATE INDEX beffroi_push_subscription_user ON beffroi_push_subscription (user_id, revoked_at)');
        $this->addSql('CREATE INDEX IDX_4F8D3B2D9033212A ON beffroi_push_subscription (tenant_id)');
        $this->addSql('CREATE INDEX IDX_4F8D3B2DA76ED395 ON beffroi_push_subscription (user_id)');
        $this->addSql('ALTER TABLE beffroi_push_subscription ADD CONSTRAINT FK_4F8D3B2D9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_push_subscription ADD CONSTRAINT FK_4F8D3B2DA76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_push_subscription');
    }
}
