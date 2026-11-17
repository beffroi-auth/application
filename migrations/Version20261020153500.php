<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The rows already held are bound to no browser and live minutes, so they are deleted and the column is mandatory.
 */
final class Version20261020153500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The browser a chain of logout of the SAML identity provider is bound to (issue #735).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DELETE FROM beffroi_saml_logout_chain');
        $this->addSql('ALTER TABLE beffroi_saml_logout_chain ADD secret CHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_saml_logout_chain DROP secret');
    }
}
