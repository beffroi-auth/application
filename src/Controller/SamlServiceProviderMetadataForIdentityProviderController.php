<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Saml\IdentityProviderSegment;
use App\Saml\ServiceProviderRoutes;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\Metadata\ServiceProviderMetadataHandler;
use Beffroi\Saml\Domain\Metadata\PublishedMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md S-65, section 6.12
 */
#[AsController]
final readonly class SamlServiceProviderMetadataForIdentityProviderController
{
    public function __construct(
        private TenantProvider $tenants,
        private ?ServiceProviderMetadataHandler $handler = null,
    ) {
    }

    #[Route(
        ServiceProviderRoutes::METADATA_FOR_IDENTITY_PROVIDER_PATH,
        name: ServiceProviderRoutes::METADATA_FOR_IDENTITY_PROVIDER,
        requirements: [
            'identityProvider' => IdentityProviderSegment::REQUIREMENT,
        ],
        methods: ['GET'],
    )]
    public function __invoke(string $identityProvider): Response
    {
        if (! $this->handler instanceof ServiceProviderMetadataHandler) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        }
        $entityId = IdentityProviderSegment::entityIdOf($identityProvider);
        if ($entityId === null) {
            throw new NotFoundHttpException('The service provider of this tenant gives no metadata to this identity provider.');
        }
        try {
            $metadata = $this->handler->handleFor($this->tenants->provide(), $entityId);
        } catch (KeyNotFoundException $exception) {
            throw new NotFoundHttpException('The service provider of this tenant gives no metadata to this identity provider.', $exception);
        }

        return new Response($metadata->document, Response::HTTP_OK, [
            'Content-Type' => PublishedMetadata::CONTENT_TYPE,
        ] + $metadata->headers);
    }
}
