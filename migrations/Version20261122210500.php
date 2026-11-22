<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122210500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The confirmations that establish the email address and the phone number of a person (NIST SP 800-63B-4 section 4.2.1.2, decision 0026).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_email_confirmation (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, code JSONB NOT NULL, link JSONB NOT NULL, failures INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, spent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7C5CAEB79033212A ON beffroi_email_confirmation (tenant_id)');
        $this->addSql('CREATE INDEX beffroi_email_confirmation_expiry ON beffroi_email_confirmation (tenant_id, expires_at)');
        $this->addSql('CREATE INDEX beffroi_email_confirmation_user ON beffroi_email_confirmation (user_id)');
        $this->addSql('ALTER TABLE beffroi_email_confirmation ADD CONSTRAINT FK_7C5CAEB79033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_email_confirmation ADD CONSTRAINT FK_7C5CAEB7A76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE beffroi_phone_number_confirmation (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, code JSONB NOT NULL, link JSONB NOT NULL, failures INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, spent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_50F9891F9033212A ON beffroi_phone_number_confirmation (tenant_id)');
        $this->addSql('CREATE INDEX beffroi_phone_number_confirmation_expiry ON beffroi_phone_number_confirmation (tenant_id, expires_at)');
        $this->addSql('CREATE INDEX beffroi_phone_number_confirmation_user ON beffroi_phone_number_confirmation (user_id)');
        $this->addSql('ALTER TABLE beffroi_phone_number_confirmation ADD CONSTRAINT FK_50F9891F9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id)');
        $this->addSql('ALTER TABLE beffroi_phone_number_confirmation ADD CONSTRAINT FK_50F9891FA76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_phone_number_confirmation');
        $this->addSql('DROP TABLE beffroi_email_confirmation');
    }
}
