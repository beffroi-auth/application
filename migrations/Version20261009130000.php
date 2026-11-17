<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use function sprintf;

final class Version20261009130000 extends AbstractMigration
{
    private const array TABLES = [
        'beffroi_authorization_code',
        'beffroi_refresh_token',
        'beffroi_access_token',
    ];

    public function getDescription(): string
    {
        return 'The embeddables of the mapping become one column each, read back by the DBAL type of their object (issue #440).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP authenticated_at', $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP amr', $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP acr', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD authentication JSONB NOT NULL', $table));
        }

        $this->addSql('ALTER TABLE beffroi_password_credential DROP hash_algorithm');
        $this->addSql('ALTER TABLE beffroi_password_credential DROP hash_encoded');
        $this->addSql('ALTER TABLE beffroi_password_credential DROP hash_parameters');
        $this->addSql('ALTER TABLE beffroi_password_credential ADD hash JSONB NOT NULL');

        // Renamed first: an index and a table share one namespace, and the new table takes the name of this index.
        $this->addSql('ALTER INDEX beffroi_refresh_token_family RENAME TO beffroi_refresh_token_family_id');
        $this->addSql('DROP INDEX beffroi_refresh_token_granting_record');
        $this->addSql('CREATE TABLE beffroi_refresh_token_family (id UUID NOT NULL, tenant_id UUID NOT NULL, granting_record_hash CHAR(64) NOT NULL, grant_id UUID NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DEB3C6789033212A ON beffroi_refresh_token_family (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX beffroi_refresh_token_family_granting_record ON beffroi_refresh_token_family (granting_record_hash)');
        $this->addSql('ALTER TABLE beffroi_refresh_token_family ADD CONSTRAINT FK_DEB3C6789033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP family_granting_record_hash');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP family_grant_id');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP family_started_at');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP family_expires_at');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD CONSTRAINT FK_574B97DEC35E566A FOREIGN KEY (family_id) REFERENCES beffroi_refresh_token_family (id)');

        $this->addSql('ALTER TABLE beffroi_user DROP name');
        $this->addSql('ALTER TABLE beffroi_user DROP given_name');
        $this->addSql('ALTER TABLE beffroi_user DROP family_name');
        $this->addSql('ALTER TABLE beffroi_user DROP middle_name');
        $this->addSql('ALTER TABLE beffroi_user DROP nickname');
        $this->addSql('ALTER TABLE beffroi_user DROP preferred_username');
        $this->addSql('ALTER TABLE beffroi_user DROP profile_url');
        $this->addSql('ALTER TABLE beffroi_user DROP picture_url');
        $this->addSql('ALTER TABLE beffroi_user DROP website_url');
        $this->addSql('ALTER TABLE beffroi_user DROP gender');
        $this->addSql('ALTER TABLE beffroi_user DROP birthdate');
        $this->addSql('ALTER TABLE beffroi_user DROP zoneinfo');
        $this->addSql('ALTER TABLE beffroi_user DROP locale');
        $this->addSql('ALTER TABLE beffroi_user ADD profile BYTEA NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP authentication', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD authenticated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD amr TEXT NOT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD acr VARCHAR(255) NOT NULL', $table));
        }

        $this->addSql('ALTER TABLE beffroi_password_credential DROP hash');
        $this->addSql('ALTER TABLE beffroi_password_credential ADD hash_algorithm VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_password_credential ADD hash_encoded TEXT NOT NULL');
        $this->addSql('ALTER TABLE beffroi_password_credential ADD hash_parameters JSONB NOT NULL');

        $this->addSql('ALTER TABLE beffroi_refresh_token DROP CONSTRAINT FK_574B97DEC35E566A');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD family_granting_record_hash CHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD family_grant_id UUID NOT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD family_started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD family_expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER INDEX beffroi_refresh_token_family_id RENAME TO beffroi_refresh_token_family');
        $this->addSql('CREATE INDEX beffroi_refresh_token_granting_record ON beffroi_refresh_token (family_granting_record_hash)');
        $this->addSql('DROP TABLE beffroi_refresh_token_family');

        $this->addSql('ALTER TABLE beffroi_user DROP profile');
        $this->addSql('ALTER TABLE beffroi_user ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD given_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD family_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD middle_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD nickname VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD preferred_username VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD profile_url TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD picture_url TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD website_url TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD gender VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD birthdate VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD zoneinfo VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD locale VARCHAR(35) DEFAULT NULL');
    }
}
