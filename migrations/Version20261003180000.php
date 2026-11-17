<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use function array_keys;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;
use function sprintf;

final class Version20261003180000 extends SealingMigration
{
    /**
     * @var array<string, array<string, string>> table, then column, then its declaration
     */
    private const array CLEARTEXT = [
        'beffroi_interaction' => [
            'login_hint' => 'TEXT DEFAULT NULL',
            'id_token_hint' => 'TEXT DEFAULT NULL',
        ],
        'beffroi_pushed_authorization_request' => [
            'parameters' => 'JSONB NOT NULL',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const array SEALED = [
        'login_hint' => 'BYTEA DEFAULT NULL',
        'id_token_hint' => 'BYTEA DEFAULT NULL',
        'parameters' => 'BYTEA NOT NULL',
    ];

    /**
     * @return list<string>
     */
    protected function tablesToSeal(): array
    {
        return array_keys(self::CLEARTEXT);
    }

    public function getDescription(): string
    {
        return 'The two hints of a request and the parameters of a pushed one, sealed (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::CLEARTEXT as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
                $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $column, self::SEALED[$column]));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::CLEARTEXT as $table => $columns) {
            foreach ($columns as $column => $declaration) {
                $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
                $this->addSql(sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $declaration));
            }
        }
    }
}
