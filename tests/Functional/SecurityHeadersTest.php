<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Http\BrowserPreferences;
use function implode;
use function parse_str;
use function parse_url;
use const PHP_URL_QUERY;
use function sprintf;
use App\Http\FormTarget;
use Beffroi\Oidc\Domain\Authorization\FormPostResponse;
use Beffroi\Oidc\Domain\Client\RedirectUri;
use Nelmio\SecurityBundle\Test\SecurityHeadersAssertionsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersTest extends BrowserTestCase
{
    use SecurityHeadersAssertionsTrait;

    private const string BROWSER_ORIGIN = 'https://client.example.com';

    /**
     * RFC 9700 §4.16
     */
    #[Test]
    #[DataProvider('provideAnswersOfEveryPlane')]
    public function itSendsTheBaselineHeadersWhateverPlaneAnswers(string $path): void
    {
        // When
        $this->browser->request('GET', $path);

        // Then
        static::assertContentTypeOptions();
        static::assertFrameOptions('DENY');
        static::assertReferrerPolicy(['no-referrer']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnswersOfEveryPlane(): iterable
    {
        yield 'the discovery document of the protocol plane' => ['/.well-known/openid-configuration'];
        yield 'a page of the interaction plane' => ['/login'];
        yield 'a script the provider serves' => ['/passkey.js'];
    }

    /**
     * RFC 6797 §6.1
     */
    #[Test]
    public function itAsksForHttpsForAYearIncludingTheSubdomainsOfTheIssuer(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        static::assertStrictTransportSecurity(maxAge: 31536000, includeSubDomains: true, preload: true);
    }

    #[Test]
    public function itAnswersTheProtocolPlaneWithTheOneCrossOriginHeaderThatAppliesToIt(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        static::assertCrossOriginResourcePolicy('same-origin');
        static::assertFalse($this->browser->getResponse()->headers->has('Cross-Origin-Embedder-Policy'));
        static::assertFalse($this->browser->getResponse()->headers->has('Cross-Origin-Opener-Policy'));
    }

    #[Test]
    #[DataProvider('providePagesARelyingPartyDrives')]
    public function itLeavesTheOpenerOfThePagesARelyingPartyDrives(string $path): void
    {
        // When
        $this->browser->request('GET', $path);

        // Then
        $headers = $this->browser->getResponse()->headers;
        static::assertFalse($headers->has('Cross-Origin-Opener-Policy'), (string) $headers->get('Cross-Origin-Opener-Policy'));
        static::assertFalse($headers->has('Cross-Origin-Embedder-Policy'), (string) $headers->get('Cross-Origin-Embedder-Policy'));
        static::assertCrossOriginResourcePolicy('same-origin');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePagesARelyingPartyDrives(): iterable
    {
        yield 'the login an authorization request sends a browser to' => ['/login'];
        yield 'the authorization endpoint itself, answering a request it refuses' => ['/authorize'];
    }

    #[Test]
    public function itIsolatesThePagesOfTheAccount(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $this->browser->request('GET', '/account');

        // Then
        static::assertIsIsolated();
    }

    #[Test]
    public function itAdmitsOnThePageOfThePasskeysTheImagesOfDataUrls(): void
    {
        // Given
        $this->signIn(self::user());

        // When
        $this->browser->request('GET', '/account/passkeys');

        // Then
        static::assertResponseIsSuccessful();
        static::assertCspHeader(requiredDirectives: ['img-src'], contains: ["img-src 'self' data:"]);
    }

    /**
     * CSP Level 3 §6.1
     */
    #[Test]
    public function itLoadsTheFontOfThePagesFromItsOwnOriginAlone(): void
    {
        // When
        $this->browser->request('GET', '/login');

        // Then
        static::assertCspHeader(requiredDirectives: ['font-src'], contains: ["font-src 'self';"]);
    }

    /**
     * CSP Level 3 §6.1
     */
    #[Test]
    #[DataProvider('provideAnswersTheDeploymentWritesThePolicyOf')]
    public function itRefusesEverythingItDoesNotName(string $path): void
    {
        // When
        $this->browser->request('GET', $path);

        // Then
        static::assertCspHeader(
            requiredDirectives: ['default-src', 'base-uri', 'script-src', 'style-src', 'form-action', 'frame-ancestors'],
            contains: ["default-src 'none'", "frame-ancestors 'none'", "script-src 'self'", "base-uri 'none'", "form-action 'self'"],
            notContains: ["'unsafe-inline'", "'unsafe-eval'"],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnswersTheDeploymentWritesThePolicyOf(): iterable
    {
        yield 'the discovery document, which carries no policy of its own' => ['/.well-known/openid-configuration'];
        yield 'a page of the interaction plane, which carries the floor of its plane' => ['/login'];
        yield 'the authorization endpoint, answering a request it refuses' => ['/authorize'];
    }

    /**
     * OAuth 2.0 Form Post Response Mode §2, CSP Level 3 §6.6.2.2
     */
    #[Test]
    public function itLeavesThePolicyOfAPageThatComputesItsOwn(): void
    {
        // When / Then
        static::assertStringStartsWith(
            "default-src 'none'; script-src 'sha256-",
            new FormPostResponse(
                new RedirectUri('https://client.example.com/callback'),
                ['code' => 'a-code', 'iss' => 'https://op.localhost'],
            )->contentSecurityPolicy(),
        );
    }

    /**
     * CSP Level 3 §6.1, §6.3.2
     */
    #[Test]
    public function itNamesTheClientAPageMayAnswerAmongTheFormTargetsOfThePolicyItWrites(): void
    {
        // Given
        $request = Request::create('https://op.localhost/login?interaction=the-one-this-browser-began', server: ['HTTPS' => 'on']);
        new FormTarget()->mayEndAt($request, new RedirectUri('https://client.example.com/callback'));
        $response = new Response('a page of the interaction plane');

        // When
        self::service('event_dispatcher', EventDispatcherInterface::class)->dispatch(
            new ResponseEvent($this->browser->getKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response),
            KernelEvents::RESPONSE,
        );

        // Then
        static::assertStringContainsString(
            "form-action 'self' https://client.example.com;",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    #[Test]
    public function itServesTheConsentPageThePolicyOfThisDeploymentCompletedWithWhatThatPageAdds(): void
    {
        // Given
        $this->signIn(self::user());
        $this->authorize();

        // When
        $this->browser->request('GET', '/consent', [
            'interaction' => $this->interactionOfTheRedirection(),
        ]);

        // Then
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCspHeader(
            requiredDirectives: ['default-src', 'base-uri', 'script-src', 'style-src', 'img-src', 'form-action', 'frame-ancestors'],
            contains: [
                "default-src 'none'",
                "img-src 'self' data: https://cdn.consent.test https://op.test",
                "form-action 'self' https://consent.test",
            ],
        );
    }

    private function interactionOfTheRedirection(): string
    {
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), self::string($response->getContent()));
        $query = [];
        parse_str((string) parse_url(self::string($response->headers->get('Location')), PHP_URL_QUERY), $query);
        $interaction = $query['interaction'] ?? null;
        static::assertIsString($interaction);

        return $interaction;
    }

    /**
     * WebAuthn §5.9
     */
    #[Test]
    public function itOpensTheCredentialApiToItsOwnOriginAndClosesTheRest(): void
    {
        // When
        $this->browser->request('GET', '/login');

        // Then
        $policy = $this->browser->getResponse()->headers->get('Permissions-Policy');
        static::assertIsString($policy);
        static::assertStringContainsString('publickey-credentials-get=(self)', $policy);
        static::assertStringContainsString('publickey-credentials-create=(self)', $policy);
        static::assertStringContainsString('camera=()', $policy);
        static::assertStringContainsString('payment=()', $policy);
    }

    /**
     * RFC 9700 §2.6
     */
    #[Test]
    #[DataProvider('provideEndpointsABrowserBasedClientCalls')]
    public function itLetsABrowserReadTheAnswerOfTheEndpointsAClientInAPageCalls(string $path, string $method, string $headers, string $allowed): void
    {
        // When
        $this->preflight($path, $method, $headers);

        // Then
        $answer = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $answer->getStatusCode(), (string) $answer->getContent());
        static::assertSame('*', $answer->headers->get('Access-Control-Allow-Origin'));
        static::assertSame($allowed, $answer->headers->get('Access-Control-Allow-Methods'));
        static::assertFalse($answer->headers->has('Access-Control-Allow-Credentials'));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideEndpointsABrowserBasedClientCalls(): iterable
    {
        yield 'the discovery document (OIDC Discovery section 4)' => ['/.well-known/openid-configuration', 'GET', '', 'GET'];
        yield 'the keys an ID token is verified with' => ['/jwks', 'GET', '', 'GET'];
        yield 'the token endpoint (RFC 6749 section 3.2)' => ['/token', 'POST', 'content-type, dpop', 'POST'];
        yield 'the claims of the person (OIDC Core section 5.3)' => ['/userinfo', 'GET', 'authorization, dpop', 'GET, POST'];
        yield 'the revocation of a token (RFC 7009 section 2)' => ['/revoke', 'POST', 'content-type, authorization', 'POST'];
        yield 'the pushed authorization request (RFC 9126 section 2)' => ['/par', 'POST', 'content-type, dpop', 'POST'];
    }

    #[Test]
    #[DataProvider('provideEndpointsNoBrowserReads')]
    public function itAnswersNoCorsWhereNoBrowserReadsTheAnswer(string $path, string $method): void
    {
        // When
        $this->preflight($path, $method, 'content-type');

        // Then
        static::assertFalse($this->browser->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEndpointsNoBrowserReads(): iterable
    {
        yield 'the authorization endpoint, reached by a navigation' => ['/authorize', 'GET'];
        yield 'the introspection a resource server calls from its own process' => ['/introspect', 'POST'];
        yield 'the registration of a client' => ['/register', 'POST'];
        yield 'the device authorization request' => ['/device_authorization', 'POST'];
        yield 'the backchannel authentication request' => ['/backchannel_authentication', 'POST'];
    }

    #[Test]
    public function itCarriesTheSessionOfABrowserOnAHostPrefixedCookie(): void
    {
        // When
        $options = static::getContainer()->getParameter('session.storage.options');

        // Then
        static::assertIsArray($options);
        static::assertSame('__Host-beffroi_session', $options['name'] ?? null);
        static::assertTrue($options['cookie_secure'] ?? null);
        static::assertTrue($options['cookie_httponly'] ?? null);
        static::assertSame(Cookie::SAMESITE_LAX, $options['cookie_samesite'] ?? null);
        static::assertSame('/', $options['cookie_path'] ?? null);
        static::assertArrayNotHasKey('cookie_domain', $options);
    }

    #[Test]
    public function itKeepsThePreferencesOfABrowserOnAHostPrefixedCookieToo(): void
    {
        // When
        $this->browser->request('POST', '/theme', ['theme' => 'dark']);

        // Then
        $cookie = $this->cookieNamed(BrowserPreferences::COOKIE);
        static::assertTrue($cookie->isSecure());
        static::assertTrue($cookie->isHttpOnly());
        static::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        static::assertSame('/', $cookie->getPath());
        static::assertNull($cookie->getDomain());
    }

    /**
     * Fetch §3.2.3
     */
    private function preflight(string $path, string $method, string $headers): void
    {
        $this->browser->request('OPTIONS', $path, server: [
            'HTTP_ORIGIN' => self::BROWSER_ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
        ] + ($headers === '' ? [] : ['HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => $headers]));
    }

    private function cookieNamed(string $name): Cookie
    {
        $names = [];
        foreach ($this->browser->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
            $names[] = $cookie->getName();
        }

        static::fail(sprintf('The answer carries no "%s"; it set %s.', $name, $names === [] ? 'no cookie' : implode(', ', $names)));
    }
}
