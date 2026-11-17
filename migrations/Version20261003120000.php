<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use function array_keys;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;
use function sprintf;

final class Version20261003120000 extends SealingMigration
{
    /**
     * @var array<string, array<string, array{string, bool}>> table, then column, then its declaration and
     *                                                        whether it accepts a null
     */
    private const array CLEARTEXT = [
        'beffroi_passkey_credential' => [
            'name' => ['VARCHAR(255)', false],
        ],
        'beffroi_push_subscription' => [
            'endpoint' => ['TEXT', false],
            'label' => ['VARCHAR(255)', true],
        ],
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
        return 'The name of a passkey, and the endpoint and the label of a push subscription, sealed (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::CLEARTEXT as $table => $columns) {
            foreach ($columns as $column => [, $nullable]) {
                $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
                $this->addSql(sprintf('ALTER TABLE %s ADD %s BYTEA %s', $table, $column, $nullable ? 'DEFAULT NULL' : 'NOT NULL'));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::CLEARTEXT as $table => $columns) {
            foreach ($columns as $column => [$declaration, $nullable]) {
                $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
                $this->addSql(sprintf(
                    'ALTER TABLE %s ADD %s %s %s',
                    $table,
                    $column,
                    $declaration,
                    $nullable ? 'DEFAULT NULL' : 'NOT NULL',
                ));
            }
        }
    }
}
