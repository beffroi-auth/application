<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Saml\ServiceProviderRoutes;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\Metadata\ServiceProviderMetadataHandler;
use Beffroi\Saml\Domain\Metadata\PublishedMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md section 6.9
 */
#[AsController]
final readonly class SamlServiceProviderMetadataController
{
    public function __construct(
        private TenantProvider $tenants,
        private ?ServiceProviderMetadataHandler $handler = null,
    ) {
    }

    #[Route(ServiceProviderRoutes::METADATA_PATH, name: ServiceProviderRoutes::METADATA, methods: ['GET'])]
    public function __invoke(): Response
    {
        if (! $this->handler instanceof ServiceProviderMetadataHandler) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        }
        try {
            $metadata = $this->handler->handle($this->tenants->provide());
        } catch (KeyNotFoundException $exception) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.', $exception);
        }

        return new Response($metadata->document, Response::HTTP_OK, [
            'Content-Type' => PublishedMetadata::CONTENT_TYPE,
        ] + $metadata->headers);
    }
}
