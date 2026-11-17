<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The clients of the administration API of a tenant (decision 0038): whether an initial access token was issued for
 * the administration of its tenant, and whether a registered client was registered by such a token, which gives it
 * that API when its row is read.
 *
 * `DEFAULT false` on both, so that a token and a client a deployment already holds speak the columns the day they
 * appear, and say what they were: issued, and registered, for no administration.
 *
 * Neither holds a personal datum, and neither is sealed.
 */
final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The initial access tokens issued for the administration, and the clients they registered (decision 0038).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_initial_access_token ADD administration BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE beffroi_client ADD administration BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_client DROP administration');
        $this->addSql('ALTER TABLE beffroi_initial_access_token DROP administration');
    }
}
