<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The rows already held asked for no class and live minutes, so they are deleted and the column is mandatory.
 */
final class Version20260927140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The classes a backchannel authentication request asks its user to prove.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DELETE FROM beffroi_backchannel_authentication_request');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD acr_values JSONB NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP acr_values');
    }
}
