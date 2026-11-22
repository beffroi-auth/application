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

    /**
     * RFC 8705 §2, RFC 8414 §2
     */
    #[Test]
    public function itAnnouncesNoMethodThatReadsACertificateForAHostTheDeploymentDidNotName(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = json_decode(self::string($response->getContent()), true);
        static::assertIsArray($document);
        foreach ([
            'token_endpoint_auth_methods_supported',
            'introspection_endpoint_auth_methods_supported',
            'revocation_endpoint_auth_methods_supported',
        ] as $member) {
            static::assertIsArray($document[$member] ?? null, $member);
            static::assertNotContains('tls_client_auth', $document[$member], $member);
            static::assertNotContains('self_signed_tls_client_auth', $document[$member], $member);
        }
    }

    /**
     * RFC 8705 §3.3
     */
    #[Test]
    public function itBindsNoTokenToACertificateForAHostTheDeploymentDidNotName(): void
    {
        // When
        $this->browser->request('GET', '/.well-known/openid-configuration');

        // Then
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $document = json_decode(self::string($response->getContent()), true);
        static::assertIsArray($document);
        static::assertFalse($document['tls_client_certificate_bound_access_tokens'] ?? null);
    }
}
