<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The rows already held name no grant and live minutes, so they are deleted and the column is mandatory.
 */
final class Version20260926220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The grant a device authorization request and a backchannel authentication request name.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DELETE FROM beffroi_device_authorization_request');
        $this->addSql('ALTER TABLE beffroi_device_authorization_request ADD grant_id UUID NOT NULL');
        $this->addSql('ALTER TABLE beffroi_device_authorization_request ADD revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX beffroi_device_authorization_grant ON beffroi_device_authorization_request (grant_id)');

        $this->addSql('DELETE FROM beffroi_backchannel_authentication_request');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD grant_id UUID NOT NULL');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX beffroi_backchannel_authentication_grant ON beffroi_backchannel_authentication_request (grant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP INDEX beffroi_backchannel_authentication_grant');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP revoked_at');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP grant_id');
        $this->addSql('DROP INDEX beffroi_device_authorization_grant');
        $this->addSql('ALTER TABLE beffroi_device_authorization_request DROP revoked_at');
        $this->addSql('ALTER TABLE beffroi_device_authorization_request DROP grant_id');
    }
}
