<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'What the enrolment of a passkey asks of its authenticator in a tenant, and what it proved of a passkey (decision 0046).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_tenant ADD passkey_attestation JSON DEFAULT \'{"policy":"none","roots":[],"own_roots":[],"models_under_alert":[]}\' NOT NULL');
        $this->addSql("ALTER TABLE beffroi_passkey_credential ADD model_proof VARCHAR(16) DEFAULT 'declared' NOT NULL");
        $this->addSql('ALTER TABLE beffroi_passkey_credential ADD serial_number BYTEA DEFAULT NULL');
        $this->addSql('ALTER TABLE beffroi_passkey_credential ADD under_security_alert BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_passkey_credential DROP under_security_alert');
        $this->addSql('ALTER TABLE beffroi_passkey_credential DROP serial_number');
        $this->addSql('ALTER TABLE beffroi_passkey_credential DROP model_proof');
        $this->addSql('ALTER TABLE beffroi_tenant DROP passkey_attestation');
    }
}
