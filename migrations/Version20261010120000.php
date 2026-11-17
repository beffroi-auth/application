<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use function array_keys;
use function sprintf;

final class Version20261010120000 extends AbstractMigration
{
    private const array EXPIRY_INDEXES = [
        'beffroi_authentication_session_expiry' => 'beffroi_authentication_session',
        'beffroi_interaction_expiry' => 'beffroi_interaction',
        'beffroi_pushed_authorization_expiry' => 'beffroi_pushed_authorization_request',
        'beffroi_refresh_token_family_expiry' => 'beffroi_refresh_token_family',
    ];

    public function getDescription(): string
    {
        return 'The four indexes the sweep of the dead rows reads (issue #177).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::EXPIRY_INDEXES as $index => $table) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (expires_at)', $index, $table));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (array_keys(self::EXPIRY_INDEXES) as $index) {
            $this->addSql(sprintf('DROP INDEX %s', $index));
        }
    }
}
