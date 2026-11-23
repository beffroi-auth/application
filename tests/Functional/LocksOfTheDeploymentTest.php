<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use function is_numeric;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

/**
 * Decision 0053: a lock one process of the deployment takes is held in the database every other process reads.
 */
final class LocksOfTheDeploymentTest extends KernelTestCase
{
    #[Test]
    public function aLockOfTheProviderIsHeldInTheDatabaseOfTheDeployment(): void
    {
        // Given
        $locks = self::getContainer()->get('lock.factory');
        static::assertInstanceOf(LockFactory::class, $locks);
        $lock = $locks->createLock('beffroi.client_assertions.the-identifier');
        $before = self::advisoryLocksOfTheDatabase();

        // When
        $lock->acquire();

        // Then
        static::assertSame($before + 1, self::advisoryLocksOfTheDatabase());
    }

    private static function advisoryLocksOfTheDatabase(): int
    {
        $database = self::getContainer()->get('doctrine.dbal.default_connection');
        static::assertInstanceOf(Connection::class, $database);
        $count = $database->fetchOne("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND database = (SELECT oid FROM pg_database WHERE datname = current_database())");

        return is_numeric($count) ? (int) $count : static::fail('PostgreSQL did not count its locks.');
    }
}
