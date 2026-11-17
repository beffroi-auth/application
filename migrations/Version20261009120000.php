<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'What a claim of a person used to say, and what they made it say (issue #482).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_claim_change (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, claim VARCHAR(64) NOT NULL, was BYTEA DEFAULT NULL, became BYTEA DEFAULT NULL, changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, context BYTEA NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_647F8D619033212A ON beffroi_claim_change (tenant_id)');
        $this->addSql('CREATE INDEX IDX_647F8D61A76ED395 ON beffroi_claim_change (user_id)');
        $this->addSql('CREATE INDEX beffroi_claim_change_person ON beffroi_claim_change (user_id, id)');
        $this->addSql('CREATE INDEX beffroi_claim_change_kept_until ON beffroi_claim_change (tenant_id, changed_at)');
        $this->addSql('ALTER TABLE beffroi_claim_change ADD CONSTRAINT FK_647F8D619033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beffroi_claim_change ADD CONSTRAINT FK_647F8D61A76ED395 FOREIGN KEY (user_id) REFERENCES beffroi_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_claim_change');
    }
}
