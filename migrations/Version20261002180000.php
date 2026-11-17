<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use function array_keys;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use App\Doctrine\Migration\SealingMigration;
use function sprintf;

final class Version20261002180000 extends SealingMigration
{
    /**
     * @var array<string, array<string, list<string>>> table, then the column gained, then the ones lost
     */
    private const array PROVENANCES = [
        'beffroi_authentication_session' => [
            'created_from' => [
                'created_from_received_at',
                'created_from_ip_address',
                'created_from_user_agent',
                'created_from_location',
                'created_from_device',
            ],
            'last_seen_from' => [
                'last_seen_from_received_at',
                'last_seen_from_ip_address',
                'last_seen_from_user_agent',
                'last_seen_from_location',
                'last_seen_from_device',
            ],
        ],
        'beffroi_device_authorization_request' => [
            'requested_from' => [
                'requested_from_received_at',
                'requested_from_ip_address',
                'requested_from_user_agent',
                'requested_from_location',
                'requested_from_device',
            ],
        ],
        'beffroi_backchannel_authentication_request' => [
            'requested_from' => [
                'requested_from_received_at',
                'requested_from_ip_address',
                'requested_from_user_agent',
                'requested_from_location',
                'requested_from_device',
            ],
        ],
    ];

    /**
     * @var array<string, string> the suffix of the column, then its declaration
     */
    private const array CLEARTEXT = [
        'received_at' => 'TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL',
        'ip_address' => 'VARCHAR(45) DEFAULT NULL',
        'user_agent' => 'VARCHAR(1024) DEFAULT NULL',
        'location' => 'JSONB DEFAULT NULL',
        'device' => 'JSONB DEFAULT NULL',
    ];

    /**
     * @return list<string>
     */
    protected function tablesToSeal(): array
    {
        return array_keys(self::PROVENANCES);
    }

    public function getDescription(): string
    {
        return 'The provenance of a request as one sealed column, and the binding message of a backchannel request (decisions 0011 and 0018).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        foreach (self::PROVENANCES as $table => $provenances) {
            foreach ($provenances as $column => $lost) {
                foreach ($lost as $old) {
                    $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $old));
                }
                $this->addSql(sprintf('ALTER TABLE %s ADD %s BYTEA NOT NULL', $table, $column));
            }
        }

        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP binding_message');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD binding_message BYTEA DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request DROP binding_message');
        $this->addSql('ALTER TABLE beffroi_backchannel_authentication_request ADD binding_message VARCHAR(255) DEFAULT NULL');

        foreach (self::PROVENANCES as $table => $provenances) {
            foreach (array_keys($provenances) as $column) {
                $this->addSql(sprintf('ALTER TABLE %s DROP %s', $table, $column));
                foreach (self::CLEARTEXT as $suffix => $declaration) {
                    $this->addSql(sprintf('ALTER TABLE %s ADD %s_%s %s', $table, $column, $suffix, $declaration));
                }
            }
        }
    }
}
