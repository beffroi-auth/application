<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Doctrine\Repository\DoctrineChainOfActs;
use Beffroi\Core\Domain\Journal\Link;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use function sprintf;

/**
 * The acts the journal already holds are chained in the order of their identifiers, a UUIDv7, so that the first
 * signature finds every act in the chain.
 */
final class Version20261123200600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The chain of the acts of the journal of each tenant, and its head (decision 0060, issue #1206).';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('CREATE TABLE beffroi_journal_head (position BIGINT NOT NULL, hash VARCHAR(64) NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (tenant_id))');
        $this->addSql('CREATE TABLE beffroi_journal_link (position BIGINT NOT NULL, previous VARCHAR(64) NOT NULL, hash VARCHAR(64) NOT NULL, act_id UUID NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (act_id))');
        $this->addSql('CREATE UNIQUE INDEX beffroi_journal_link_position ON beffroi_journal_link (tenant_id, position)');
        $this->addSql('CREATE INDEX IDX_353F7B769033212A ON beffroi_journal_link (tenant_id)');
        $this->addSql('ALTER TABLE beffroi_journal_head ADD CONSTRAINT FK_A460141B9033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beffroi_journal_link ADD CONSTRAINT FK_353F7B76D1A55B28 FOREIGN KEY (act_id) REFERENCES beffroi_journal (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE beffroi_journal_link ADD CONSTRAINT FK_353F7B769033212A FOREIGN KEY (tenant_id) REFERENCES beffroi_tenant (id) ON DELETE CASCADE');
    }

    public function postUp(Schema $schema): void
    {
        foreach ($this->connection->fetchFirstColumn('SELECT DISTINCT tenant_id FROM beffroi_journal') as $tenant) {
            $head = Link::beforeTheFirst();
            $acts = $this->connection->fetchAllAssociative(
                sprintf('SELECT %s FROM beffroi_journal j WHERE j.tenant_id = :tenant ORDER BY j.id', DoctrineChainOfActs::ACT),
                ['tenant' => $tenant],
            );
            foreach ($acts as $act) {
                $link = $head->then(DoctrineChainOfActs::writtenAct($this->connection, $act));
                $this->connection->insert(DoctrineChainOfActs::LINKS, [
                    'act_id' => $act['id'],
                    'tenant_id' => $tenant,
                    'position' => $link->position,
                    'previous' => $head->hash,
                    'hash' => $link->hash,
                ]);
                $head = $link;
            }
            $this->connection->insert(DoctrineChainOfActs::HEADS, [
                'tenant_id' => $tenant,
                'position' => $head->position,
                'hash' => $head->hash,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(! $this->platform instanceof PostgreSQLPlatform, 'This migration is written for PostgreSQL.');

        $this->addSql('DROP TABLE beffroi_journal_link');
        $this->addSql('DROP TABLE beffroi_journal_head');
    }
}
