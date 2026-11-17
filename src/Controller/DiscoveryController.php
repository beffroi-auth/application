<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use Beffroi\Oidc\Application\Discovery\DiscoveryHandler;
use Beffroi\Oidc\Domain\Discovery\ProviderMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OIDC Discovery §4, RFC 8414 §3
 *
 * The issuer of a tenant being an origin, the two well-known locations are the same.
 */
#[AsController]
final readonly class DiscoveryController
{
    public function __construct(
        private TenantProvider $tenants,
        private DiscoveryHandler $handler,
    ) {
    }

    #[Route(ProviderMetadata::OPENID_CONFIGURATION_PATH, name: 'beffroi_protocol_discovery', methods: ['GET'])]
    #[Route(ProviderMetadata::AUTHORIZATION_SERVER_METADATA_PATH, name: 'beffroi_protocol_authorization_server_metadata', methods: ['GET'])]
    public function __invoke(): Response
    {
        $metadata = $this->handler->handle($this->tenants->provide());

        return new Response($metadata->toJson(), Response::HTTP_OK, [
            'Content-Type' => ProviderMetadata::CONTENT_TYPE,
        ] + $metadata->headers);
    }
}
