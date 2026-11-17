<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The resources an authorization was asked for.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction ADD resources TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_authorization_code ADD resources TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_access_token ADD resources TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD resources TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction DROP resources');
        $this->addSql('ALTER TABLE beffroi_authorization_code DROP resources');
        $this->addSql('ALTER TABLE beffroi_access_token DROP resources');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP resources');
    }
}
