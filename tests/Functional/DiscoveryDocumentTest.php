<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use function json_decode;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class DiscoveryDocumentTest extends BrowserTestCase
{
    /**
     * RFC 8705 §5
     */
    #[Test]
    public function itAnnouncesNoMutualTlsAliasForAHostTheDeploymentDidNotName(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = json_decode(self::string($response->getContent()), true);
        static::assertIsArray($document);
        static::assertArrayNotHasKey('mtls_endpoint_aliases', $document);
    }

    /**
     * RFC 7591, Decision 0009
     */
    #[Test]
    public function itAnnouncesNoRegistrationEndpointForAModeTheDeploymentDidNotName(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = json_decode(self::string($response->getContent()), true);
        static::assertIsArray($document);
        static::assertArrayNotHasKey('registration_endpoint', $document);
    }

    /**
     * RFC 9396 §10
     */
    #[Test]
    public function itAnnouncesNoTypeOfAuthorizationDetailTheDeploymentDidNotWire(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = json_decode(self::string($response->getContent()), true);
        static::assertIsArray($document);
        static::assertArrayNotHasKey('authorization_details_types_supported', $document);
    }
}
