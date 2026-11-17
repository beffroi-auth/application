<?php

declare(strict_types=1);

namespace App\Tests\Functional\Demonstration;

use function array_column;
use PHPUnit\Framework\Attributes\Test;
use function str_contains;
use Symfony\Component\HttpFoundation\Response;

final class RecordedFlowsTest extends DemonstrationTestCase
{
    private const string CLIENT_ID = 'a-client-this-provider-never-registered';

    /**
     * RFC 6749 §3.2
     */
    #[Test]
    public function aCallToTheTokenEndpointIsDrawnOnBothHalves(): void
    {
        // When
        $this->browser->request('POST', '/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
            'scope' => 'invoices.read',
        ]);

        // Then
        static::assertSame(Response::HTTP_UNAUTHORIZED, $this->browser->getResponse()->getStatusCode());
        $trace = self::traces()->of('client_credentials', self::CLIENT_ID) ?? static::fail('The call was not recorded.');
        static::assertSame(['token_request', 'token_response'], array_column($trace->hops, 'hop'));
        static::assertTrue(str_contains($trace->hops[0]->payload, 'grant_type=client_credentials'));
        static::assertSame('POST', $trace->hops[0]->method);
        static::assertSame(Response::HTTP_UNAUTHORIZED, $trace->hops[1]->status);
    }

    /**
     * RFC 6749 §5.2
     */
    #[Test]
    public function aHopThatFailedIsDrawnAsItFailed(): void
    {
        // When
        $this->browser->request('POST', '/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
        ]);

        // Then
        $trace = self::traces()->of('client_credentials', self::CLIENT_ID) ?? static::fail('The call was not recorded.');
        static::assertTrue($trace->hops[1]->failed);
        static::assertTrue(str_contains($trace->hops[1]->payload, 'invalid_client'));
    }

    #[Test]
    public function aFlowPlayedAgainIsARunOfItsOwn(): void
    {
        // Given
        $this->askTheTokenEndpoint();
        $first = self::traces()->of('client_credentials', self::CLIENT_ID) ?? static::fail('The call was not recorded.');

        // When
        $this->askTheTokenEndpoint();

        // Then
        $second = self::traces()->of('client_credentials', self::CLIENT_ID) ?? static::fail('The call was not recorded.');
        static::assertSame(['token_request', 'token_response'], array_column($second->hops, 'hop'));
        static::assertNotSame($first->startedAt->format('U.u'), $second->startedAt->format('U.u'));
    }

    #[Test]
    public function aGrantTypeTheProviderDoesNotServeIsRecordedNowhere(): void
    {
        // When
        $this->browser->request('POST', '/token', [
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
        ]);

        // Then
        static::assertNull(self::traces()->of('password', self::CLIENT_ID));
    }

    #[Test]
    public function aPageOfTheApplicationIsRecordedNowhere(): void
    {
        // When
        $this->browser->request('GET', '/account/profile');

        // Then
        static::assertSame([], self::traces()->clientsOf('authorization_code'));
    }

    #[Test]
    public function anAnswerThatKeepsTheBrowserHereIsNotTheAuthorizationResponse(): void
    {
        // When
        $this->browser->request(
            'GET',
            '/authorize?response_type=code&client_id='
            . self::CLIENT_ID
            . '&redirect_uri=https%3A%2F%2Fclient.test%2Fcb&scope=openid&code_challenge=0123456789012345678901234567890123456789012&code_challenge_method=S256',
        );

        // Then
        $trace = self::traces()->of('authorization_code', self::CLIENT_ID) ?? static::fail('The request was not recorded.');
        static::assertSame(['authorization_request'], array_column($trace->hops, 'hop'));
        static::assertTrue(str_contains($trace->hops[0]->payload, 'response_type=code'));
    }

    private function askTheTokenEndpoint(): void
    {
        $this->browser->request('POST', '/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
        ]);
    }
}
