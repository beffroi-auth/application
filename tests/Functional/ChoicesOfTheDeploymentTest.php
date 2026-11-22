<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function array_key_last;
use function explode;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Bridge\Sweego\SweegoTransport;
use Symfony\Component\Notifier\Transport\TransportInterface;
use function trim;

final class ChoicesOfTheDeploymentTest extends BrowserTestCase
{
    private const array CHOICES = [
        'BEFFROI_REGISTRATION_MODE' => 'initial_access_token',
        'BEFFROI_TENANT_NAME' => 'Example Corporation',
        'BEFFROI_TENANT_LOGO_URI' => 'https://assets.example.com/mark.svg',
        'BEFFROI_TENANT_PRIMARY_COLOR' => '#0b5d1e',
        'BEFFROI_TENANT_PRIMARY_CONTENT_COLOR' => '#fafafa',
        'BEFFROI_TENANT_SELF_SERVED_CLAIMS' => '["nickname","locale"]',
        'BEFFROI_TENANT_SCOPES' => '["invoices.read","invoices.pay"]',
        'BEFFROI_SAML_IDENTITY_PROVIDER' => 'false',
        'BEFFROI_INTERACTION_LOGIN_HINT' => 'username',
        'BEFFROI_SMS_DSN' => 'sweego://an-api-key@default?region=FR&campaign_type=transac&shorten_urls=false',
    ];

    /**
     * @var array<string, array{mixed, mixed}>
     */
    private array $before = [];

    protected function setUp(): void
    {
        foreach (self::CHOICES as $name => $value) {
            $this->before[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->before as $name => [$server, $environment]) {
            $_SERVER[$name] = $server;
            $_ENV[$name] = $environment;
        }
    }

    /**
     * RFC 7591, Decision 0009
     */
    #[Test]
    public function theTenantAnnouncesTheRegistrationEndpointOfTheModeItNames(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $document = json_decode(self::string($this->browser->getResponse()->getContent()), true);
        static::assertIsArray($document);
        static::assertSame('https://op.test/register', $document['registration_endpoint'] ?? null);
    }

    /**
     * OIDC Discovery §3
     */
    #[Test]
    public function theTenantAnnouncesTheScopesOfTheApisItNames(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $document = json_decode(self::string($this->browser->getResponse()->getContent()), true);
        static::assertIsArray($document);
        static::assertSame(
            ['openid', 'profile', 'email', 'address', 'phone', 'invoices.read', 'invoices.pay', 'offline_access'],
            $document['scopes_supported'] ?? null,
        );
    }

    /**
     * RFC 7591 §3.1, Decision 0009
     */
    #[Test]
    public function aClientThatRegistersItselfMayRegisterTheScopesOfTheApisItNames(): void
    {
        // Given
        $token = $this->initialAccessToken();

        // When
        $this->browser->request(
            'POST',
            '/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'client_name' => 'A relying party of the deployment',
                'redirect_uris' => ['https://rp.example.com/callback'],
                'token_endpoint_auth_method' => 'client_secret_basic',
                'grant_types' => ['authorization_code', 'refresh_token'],
                'scope' => 'openid profile email offline_access invoices.read invoices.pay',
            ], JSON_THROW_ON_ERROR),
        );

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), self::string($response->getContent()));
    }

    #[Test]
    public function aPageShowsTheMarkItNamesUnderTheNameItNames(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $logo = $this->accountHome()->filter('img[data-tenant="logo"]');

        // Then
        static::assertSame('https://assets.example.com/mark.svg', $logo->attr('src'));
        static::assertSame('Example Corporation', $logo->attr('alt'));
    }

    /**
     * CSP Level 3 §6.1
     */
    #[Test]
    public function aPageMayLoadTheMarkItNamesFromTheHostThatServesIt(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $this->accountHome();

        // Then
        static::assertStringContainsString(
            "img-src 'self' data: https://assets.example.com;",
            self::string($this->browser->getResponse()->headers->get('Content-Security-Policy')),
        );
    }

    /**
     * Decision 0030
     */
    #[Test]
    public function aPersonChangesOfThemselvesTheClaimsItOpens(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $page = $this->page('/account/profile');

        // Then
        static::assertSame(['nickname', 'locale'], $page->filter('form[data-editable]')->extract(['data-editable']));
    }

    #[Test]
    public function aPageIsDrawnInTheColoursItNames(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $style = $this->accountHome()->filter('head style')->text();

        // Then
        static::assertStringContainsString('--color-primary: #0b5d1e; --color-primary-content: #fafafa;', $style);
    }

    /**
     * Decision 0030
     */
    #[Test]
    public function aDeploymentThatTurnsItsSamlRolesOffDrawsNoSamlKey(): void
    {
        // Given
        $application = new Application(self::$kernel ?? static::fail('The kernel is not booted.'));
        $application->setAutoExit(false);
        $keys = new CommandTester($application->find('beffroi:keys:generate'));

        // When
        $keys->execute([
            '--algorithm' => 'SAML-SIGNING',
        ]);

        // Then
        static::assertSame(Command::FAILURE, $keys->getStatusCode());
    }

    /**
     * Decision 0048
     */
    #[Test]
    public function aDeploymentTextsThroughTheSweegoBridgeByTheDsnItNames(): void
    {
        // When
        $transport = self::service('beffroi.sms.transport', TransportInterface::class);

        // Then
        static::assertInstanceOf(SweegoTransport::class, $transport);
    }

    /**
     * OIDC Core §3.1.2.1
     */
    #[Test]
    public function theLoginPageOffersTheUserTheHintNamesTheWayTheDeploymentReadsIt(): void
    {
        // Given
        self::user();
        $this->browser->request('GET', '/authorize', [
            'client_id' => 'consent-app',
            'redirect_uri' => 'https://consent.test/callback',
            'response_type' => 'code',
            'scope' => 'openid profile',
            'state' => 'state-of-the-client',
            'nonce' => 'nonce-of-the-client',
            'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            'code_challenge_method' => 'S256',
            'login_hint' => 'alice',
        ]);

        // When
        $page = $this->browser->followRedirect();

        // Then
        static::assertSame('alice', $page->filter('input#username')->attr('value'));
    }

    /**
     * RFC 7591 §1.2
     */
    private function initialAccessToken(): string
    {
        $application = new Application(self::$kernel ?? static::fail('The kernel is not booted.'));
        $application->setAutoExit(false);
        $issue = new CommandTester($application->find('beffroi:client:token:issue'));
        $issue->execute([
            'name' => 'The team that integrates',
        ]);
        $issue->assertCommandIsSuccessful();
        $lines = explode("\n", trim($issue->getDisplay()));

        return trim($lines[array_key_last($lines)]);
    }
}
