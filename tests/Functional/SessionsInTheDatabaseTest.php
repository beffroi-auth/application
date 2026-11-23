<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Http\DatabaseSessions;
use Doctrine\DBAL\Connection;
use function is_string;
use function json_encode;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Decision 0053: the session a server writes is the one another server of the deployment reads, in the database
 * of the deployment, under an HMAC of its identifier.
 */
final class SessionsInTheDatabaseTest extends KernelTestCase
{
    private const string COOKIE = 'w3Fz9LpQ0dXn7Ty2';

    protected function tearDown(): void
    {
        self::database()->executeStatement('DELETE FROM beffroi_session');
        parent::tearDown();
    }

    #[Test]
    public function aSessionAServerWroteIsReadByAnother(): void
    {
        // Given
        $written = self::sessions();
        $written->open('', 'beffroi');
        $written->read(self::COOKIE);
        $written->write(self::COOKIE, 'the data of the browser');
        $written->close();
        $another = DatabaseSessions::onTheConnectionOf(self::database(), self::secret());

        // When
        $another->open('', 'beffroi');
        $read = $another->read(self::COOKIE);
        $another->close();

        // Then
        static::assertSame('the data of the browser', $read);
    }

    #[Test]
    public function theTableHoldsNeitherTheIdentifierOfTheCookieNorTheData(): void
    {
        // Given
        $sessions = self::sessions();

        // When
        $sessions->open('', 'beffroi');
        $sessions->read(self::COOKIE);
        $sessions->write(self::COOKIE, 'the data of the browser');
        $sessions->close();

        // Then
        $dump = self::database()->fetchAllAssociative("SELECT sess_id, encode(sess_data, 'escape') AS sess_data FROM beffroi_session");
        static::assertCount(1, $dump);
        static::assertStringNotContainsString(self::COOKIE, (string) json_encode($dump));
        static::assertStringNotContainsString('the data of the browser', (string) json_encode($dump));
    }

    private static function sessions(): DatabaseSessions
    {
        $sessions = self::getContainer()->get(DatabaseSessions::class);
        static::assertInstanceOf(DatabaseSessions::class, $sessions);

        return $sessions;
    }

    private static function database(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        static::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private static function secret(): string
    {
        $secret = self::getContainer()->getParameter('kernel.secret');
        static::assertTrue(is_string($secret));

        return $secret;
    }
}
