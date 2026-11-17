<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'What a request asks for beyond its scopes, carried by the interaction, the consent, the code and the tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction ADD authorization_details JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_consent ADD authorization_details JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_authorization_code ADD authorization_details JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_refresh_token ADD authorization_details JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_access_token ADD authorization_details JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_interaction DROP authorization_details');
        $this->addSql('ALTER TABLE beffroi_consent DROP authorization_details');
        $this->addSql('ALTER TABLE beffroi_authorization_code DROP authorization_details');
        $this->addSql('ALTER TABLE beffroi_refresh_token DROP authorization_details');
        $this->addSql('ALTER TABLE beffroi_access_token DROP authorization_details');
    }
}
