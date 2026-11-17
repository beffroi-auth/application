<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The key an authorization is reserved for, and the binding of a refresh token.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction ADD dpop_jkt VARCHAR(43) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_authorization_code ADD dpop_jkt VARCHAR(43) DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD cnf JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction DROP dpop_jkt');
        $this->addSql('ALTER TABLE beffroi_authorization_code DROP dpop_jkt');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP cnf');
    }
}
