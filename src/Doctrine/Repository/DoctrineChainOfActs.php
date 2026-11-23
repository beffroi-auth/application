<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Journal\JournalEntry;
use Beffroi\Core\Domain\Journal\Link;
use Beffroi\Core\Domain\Journal\LinkedAct;
use Beffroi\Core\Domain\Journal\WrittenAct;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\ChainOfActsInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use function is_numeric;
use function is_string;
use function sprintf;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Through DBAL and never the manager: a link is computed from the act as the database holds it, its provenance as the
 * bytes of its seal, which the manager never hands back.
 */
final readonly class DoctrineChainOfActs implements ChainOfActsInterface
{
    public const string HEADS = 'beffroi_journal_head';

    public const string LINKS = 'beffroi_journal_link';

    /**
     * The columns of an act a link is computed from, under the alias "j".
     */
    public const string ACT = 'j.id, j.tenant_id, j.act, j.occurred_at, j.subject, j.client, j.author, j.context';

    public function __construct(
        private EntityManagers $managers,
    ) {
    }

    /**
     * An upsert takes the lock of the row and answers the head as the last writer left it, the same statement on
     * PostgreSQL and SQLite.
     */
    public function lockedHead(Tenant $tenant): Link
    {
        $row = $this->connection()->fetchAssociative(
            sprintf(
                'INSERT INTO %1$s (tenant_id, position, hash) VALUES (:tenant, 0, :hash) ON CONFLICT (tenant_id) DO UPDATE SET position = %1$s.position RETURNING position, hash',
                self::HEADS,
            ),
            ['tenant' => $tenant->id, 'hash' => Link::beforeTheFirst()->hash],
            ['tenant' => UuidType::NAME],
        );
        if ($row === false) {
            throw new RuntimeException(sprintf('The head of the journal of the tenant "%s" cannot be locked.', $tenant->slug));
        }

        return self::link($row['position'] ?? null, $row['hash'] ?? null);
    }

    public function head(Tenant $tenant): Link
    {
        $row = $this->connection()->fetchAssociative(
            sprintf('SELECT position, hash FROM %s WHERE tenant_id = :tenant', self::HEADS),
            ['tenant' => $tenant->id],
            ['tenant' => UuidType::NAME],
        );

        return $row === false ? Link::beforeTheFirst() : self::link($row['position'] ?? null, $row['hash'] ?? null);
    }

    public function linkedAfter(Tenant $tenant, int $position, int $howMany): array
    {
        $connection = $this->connection();
        $rows = $connection
            ->createQueryBuilder()
            ->select(self::ACT, 'l.position', 'l.previous', 'l.hash')
            ->from(self::LINKS, 'l')
            ->innerJoin('l', 'beffroi_journal', 'j', 'j.id = l.act_id')
            ->where('l.tenant_id = :tenant')
            ->andWhere('l.position > :position')
            ->orderBy('l.position', 'ASC')
            ->setMaxResults($howMany)
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->setParameter('position', $position)
            ->fetchAllAssociative();
        $linked = [];
        foreach ($rows as $row) {
            $position = $row['position'] ?? null;
            $previous = $row['previous'] ?? null;
            $hash = $row['hash'] ?? null;
            if (! is_numeric($position) || ! is_string($previous) || ! is_string($hash)) {
                throw new RuntimeException('A link of the journal does not read as a position and two hashes.');
            }
            $linked[] = new LinkedAct(self::writtenAct($connection, $row), (int) $position, $previous, $hash);
        }

        return $linked;
    }

    public function actsOutsideTheChain(Tenant $tenant, int $keptAfter): int
    {
        $count = $this->connection()->fetchOne(
            sprintf(
                'SELECT COUNT(*) FROM beffroi_journal j LEFT JOIN %s l ON l.act_id = j.id WHERE j.tenant_id = :tenant AND (l.act_id IS NULL OR l.position <= :keptAfter)',
                self::LINKS,
            ),
            ['tenant' => $tenant->id, 'keptAfter' => $keptAfter],
            ['tenant' => UuidType::NAME],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * In the transaction that wrote the act, under the lock lockedHead() took.
     */
    public function append(JournalEntry $entry, Link $head): Link
    {
        $connection = $this->connection();
        $row = $connection->fetchAssociative(
            sprintf('SELECT %s FROM beffroi_journal j WHERE j.id = :id', self::ACT),
            ['id' => $entry->id],
            ['id' => UuidType::NAME],
        );
        if ($row === false) {
            throw new RuntimeException('An act of the journal cannot be linked before it is written.');
        }
        $link = $head->then(self::writtenAct($connection, $row));
        $connection->insert(
            self::LINKS,
            [
                'act_id' => $entry->id,
                'tenant_id' => $entry->tenant->id,
                'position' => $link->position,
                'previous' => $head->hash,
                'hash' => $link->hash,
            ],
            ['act_id' => UuidType::NAME, 'tenant_id' => UuidType::NAME, 'position' => Types::BIGINT],
        );
        $connection->update(
            self::HEADS,
            ['position' => $link->position, 'hash' => $link->hash],
            ['tenant_id' => $entry->tenant->id],
            ['position' => Types::BIGINT, 'tenant_id' => UuidType::NAME],
        );

        return $link;
    }

    /**
     * @param array<string, mixed> $row the columns of ACT
     *
     * @throws RuntimeException
     */
    public static function writtenAct(Connection $connection, array $row): WrittenAct
    {
        $id = $connection->convertToPHPValue($row['id'] ?? null, UuidType::NAME);
        $tenant = $connection->convertToPHPValue($row['tenant_id'] ?? null, UuidType::NAME);
        $occurredAt = $connection->convertToPHPValue($row['occurred_at'] ?? null, Types::DATETIME_IMMUTABLE);
        $context = $connection->convertToPHPValue($row['context'] ?? null, Types::BINARY);
        $act = $row['act'] ?? null;
        if (! $id instanceof Uuid || ! $tenant instanceof Uuid || ! $occurredAt instanceof DateTimeImmutable || ! is_string($context) || ! is_string($act)) {
            throw new RuntimeException('A row of the journal does not read as an act.');
        }

        return new WrittenAct(
            $id->toRfc4122(),
            $tenant->toRfc4122(),
            $act,
            $occurredAt,
            self::nullableString($row['subject'] ?? null),
            self::nullableString($row['client'] ?? null),
            self::nullableString($row['author'] ?? null),
            $context,
        );
    }

    private function connection(): Connection
    {
        return $this->managers->forClass(JournalEntry::class)->getConnection();
    }

    private static function link(mixed $position, mixed $hash): Link
    {
        if (! is_numeric($position) || ! is_string($hash)) {
            throw new RuntimeException('A link of the journal does not read as a position and a hash.');
        }

        return new Link((int) $position, $hash);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw new RuntimeException('A row of the journal does not read as an act.');
        }

        return $value;
    }
}
