<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;

/**
 * The unique constraint moves onto the two blind indexes: a sealed column holds a new ciphertext at every write.
 */
final class Version20261002120000 extends SealingMigration
{
    /**
     * @return list<string>
     */
    protected function tablesToSeal(): array
    {
        return ['beffroi_linked_identity'];
    }

    public function getDescription(): string
    {
        return 'The provider, the subject and the claims of beffroi_linked_identity sealed, and the blind index of the pair (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        // The constraint goes first: it is held on two columns this migration drops.
        $this->addSql('DROP INDEX beffroi_linked_identity_subject');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP provider');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP subject');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP claims');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD provider BYTEA NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD provider_index VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD subject BYTEA NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD subject_index VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD claims BYTEA NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX beffroi_linked_identity_subject ON beffroi_linked_identity (tenant_id, provider_index, subject_index)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP INDEX beffroi_linked_identity_subject');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP provider_index');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP subject_index');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP provider');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP subject');
        $this->addSql('ALTER TABLE beffroi_linked_identity DROP claims');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD provider VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD subject VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_linked_identity ADD claims JSONB NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX beffroi_linked_identity_subject ON beffroi_linked_identity (tenant_id, provider, subject)');
    }
}
