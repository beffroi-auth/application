<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function base64_encode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use function hash;
use function implode;
use App\Doctrine\TenantProvider;
use App\Doctrine\TenantSynchronizer;
use App\Http\BrowserSession;
use Beffroi\Core\Domain\Identity\Subject;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\AuthenticationProofs;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\AuthenticationSessionRepositoryInterface;
use Beffroi\Core\Port\UserRepositoryInterface;
use function rtrim;
use function sprintf;
use function strtr;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Uid\Uuid;
use function time;

abstract class BrowserTestCase extends WebTestCase
{
    protected KernelBrowser $browser;

    protected function setUp(): void
    {
        // Over HTTPS: "nelmio_security.forced_ssl" redirects a plain request, and a "__Host-" cookie needs TLS.
        $this->browser = static::createClient([], ['HTTPS' => 'on']);
        // Services keep what they hold from one request to the next, as in production.
        $this->browser->disableReboot();
        self::emptyTheDatabase();
    }

    /**
     * Decision 0018
     */
    private static function emptyTheDatabase(): void
    {
        $connection = self::entityManager()->getConnection();
        $tables = [];
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            if ($table !== 'doctrine_migration_versions') {
                $tables[] = $connection->quoteSingleIdentifier($table);
            }
        }
        $connection->executeStatement(sprintf('TRUNCATE TABLE %s CASCADE', implode(', ', $tables)));
        self::entityManager()->clear();
        self::service('test.beffroi.tenant_synchronizer', TenantSynchronizer::class)->synchronize();
    }

    protected static function user(string $username = 'alice', string $subject = 'alice-subject'): User
    {
        $user = User::create(Uuid::v7(), self::tenant(), new Subject($subject), $username, self::now());
        self::service('test.beffroi.users', UserRepositoryInterface::class)->save($user);

        return $user;
    }

    protected function signIn(User $user): AuthenticationSession
    {
        $establishedAt = self::now();
        $session = new AuthenticationSession(
            Uuid::v7(),
            $user->tenant,
            $user,
            $establishedAt,
            AuthenticationProofs::of(AuthenticationMethod::password(), $establishedAt),
            new RequestContext($establishedAt, '127.0.0.1', 'Beffroi test browser'),
            $establishedAt->modify('+12 hours'),
        );
        self::service('test.beffroi.sessions', AuthenticationSessionRepositoryInterface::class)->save($session);
        $httpSession = self::service('session.factory', SessionFactoryInterface::class)->createSession();
        $httpSession->start();
        self::service('test.beffroi.browser_session', BrowserSession::class)->bind($httpSession, $session);
        $httpSession->save();
        $this->browser->getCookieJar()->set(new Cookie($httpSession->getName(), $httpSession->getId()));

        return $session;
    }

    protected function accountHome(): Crawler
    {
        return $this->page('/account');
    }

    protected function page(string $path): Crawler
    {
        $page = $this->browser->request('GET', $path);
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), self::string($response->getContent()));

        return $page;
    }

    protected function authorize(): void
    {
        $this->browser->request('GET', '/authorize', [
            'client_id' => 'consent-app',
            'redirect_uri' => 'https://consent.test/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'state-of-the-client',
            'nonce' => 'nonce-of-the-client',
            'code_challenge' => self::codeChallenge(),
            'code_challenge_method' => 'S256',
        ]);
    }

    protected function afterTheForm(): Crawler
    {
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode(), self::string($response->getContent()));

        return $this->browser->followRedirect();
    }

    protected static function reread(User $user): User
    {
        self::entityManager()->clear();

        return self::service('test.beffroi.users', UserRepositoryInterface::class)->find($user->id) ?? static::fail('The user is gone.');
    }

    protected static function tenant(): Tenant
    {
        return self::service('test.beffroi.tenant_provider', TenantProvider::class)->provide();
    }

    protected static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . time());
    }

    protected static function string(mixed $value): string
    {
        static::assertIsString($value);

        return $value;
    }

    /**
     * RFC 7636 §4.2
     */
    private static function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', true)), '+/', '-_'), '=');
    }

    private static function entityManager(): EntityManagerInterface
    {
        return self::service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function service(string $id, string $class): object
    {
        $service = static::getContainer()->get($id);
        static::assertInstanceOf($class, $service);

        return $service;
    }
}
