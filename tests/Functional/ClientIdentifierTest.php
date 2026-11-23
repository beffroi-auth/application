<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function base64_encode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 6749 appendix A.1
 */
final class ClientIdentifierTest extends BrowserTestCase
{
    /**
     * RFC 6749 §2.3.1, §5.2
     *
     * @param array<string, string> $parameters
     * @param array<string, string> $server
     */
    #[Test]
    #[DataProvider('provideClientIdsThatAreNotText')]
    public function aClientIdThatIsNotTextAuthenticatesNobody(array $parameters, array $server): void
    {
        // When
        $this->browser->request(
            'POST',
            '/token',
            [
                'grant_type' => 'client_credentials',
                ...$parameters,
            ],
            [],
            $server,
        );

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        static::assertStringContainsString('"error":"invalid_client"', self::string($response->getContent()));
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, string>}>
     */
    public static function provideClientIdsThatAreNotText(): iterable
    {
        yield 'in the body' => [
            [
                'client_id' => "\xff\xfe",
                'client_secret' => 'secret',
            ],
            [],
        ];
        yield 'in the Basic scheme' => [
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('%FF%FE:secret'),
            ],
        ];
    }

    /**
     * RFC 6749 §4.1.2.1
     */
    #[Test]
    public function anAuthorizationRequestWhoseClientIdIsNotTextIsRefused(): void
    {
        // When
        $this->browser->request('GET', '/authorize?client_id=%FF%FE&response_type=code&scope=openid&redirect_uri=https%3A%2F%2Fclient.example%2Fcallback');

        // Then
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
    }
}
