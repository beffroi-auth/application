<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use function array_keys;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;
use function sprintf;

/**
 * The unique constraint moves onto the blind index: a sealed column holds a new ciphertext at every write.
 */
final class Version20261001180000 extends SealingMigration
{
    private const array CLEARTEXT = [
        'email' => 'VARCHAR(255)',
        'phone_number' => 'VARCHAR(64)',
        'address' => 'JSONB',
        'name' => 'VARCHAR(255)',
        'given_name' => 'VARCHAR(255)',
        'family_name' => 'VARCHAR(255)',
        'middle_name' => 'VARCHAR(255)',
        'nickname' => 'VARCHAR(255)',
        'preferred_username' => 'VARCHAR(255)',
        'profile_url' => 'TEXT',
        'picture_url' => 'TEXT',
        'website_url' => 'TEXT',
        'gender' => 'VARCHAR(64)',
        'birthdate' => 'VARCHAR(10)',
        'zoneinfo' => 'VARCHAR(64)',
        'locale' => 'VARCHAR(35)',
    ];

    /**
     * @return list<string>
     */
    protected function tablesToSeal(): array
    {
        return ['beffroi_user'];
    }

    public function getDescription(): string
    {
        return 'The personal data of beffroi_user sealed, and the blind indexes of the username and the address (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        // The constraint goes first: it is held on a column this migration drops.
        $this->addSql('DROP INDEX beffroi_user_username');
        $this->addSql('ALTER TABLE beffroi_user DROP username');
        $this->addSql('ALTER TABLE beffroi_user ADD username BYTEA NOT NULL');

        foreach (array_keys(self::CLEARTEXT) as $column) {
            $this->addSql(sprintf('ALTER TABLE beffroi_user DROP %s', $column));
            $this->addSql(sprintf('ALTER TABLE beffroi_user ADD %s BYTEA DEFAULT NULL', $column));
        }

        $this->addSql('ALTER TABLE beffroi_user ADD username_index VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE beffroi_user ADD email_index VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX beffroi_user_username ON beffroi_user (tenant_id, username_index)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP INDEX beffroi_user_username');
        $this->addSql('ALTER TABLE beffroi_user DROP username_index');
        $this->addSql('ALTER TABLE beffroi_user DROP email_index');
        $this->addSql('ALTER TABLE beffroi_user DROP username');
        $this->addSql('ALTER TABLE beffroi_user ADD username VARCHAR(255) NOT NULL');

        foreach (self::CLEARTEXT as $column => $declaration) {
            $this->addSql(sprintf('ALTER TABLE beffroi_user DROP %s', $column));
            $this->addSql(sprintf('ALTER TABLE beffroi_user ADD %s %s DEFAULT NULL', $column, $declaration));
        }

        $this->addSql('CREATE UNIQUE INDEX beffroi_user_username ON beffroi_user (tenant_id, username)');
    }
}
