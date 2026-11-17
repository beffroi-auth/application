<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Saml\IdentityProviderRoutes;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\Metadata\IdentityProviderMetadataHandler;
use Beffroi\Saml\Domain\Metadata\PublishedMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md section 6.1
 */
#[AsController]
final readonly class SamlMetadataController
{
    public function __construct(
        private TenantProvider $tenants,
        private ?IdentityProviderMetadataHandler $handler = null,
    ) {
    }

    #[Route(IdentityProviderRoutes::METADATA_PATH, name: IdentityProviderRoutes::METADATA, methods: ['GET'])]
    public function __invoke(): Response
    {
        if (! $this->handler instanceof IdentityProviderMetadataHandler) {
            throw new NotFoundHttpException('This tenant serves no SAML identity provider.');
        }
        try {
            $metadata = $this->handler->handle($this->tenants->provide());
        } catch (KeyNotFoundException $exception) {
            throw new NotFoundHttpException('This tenant serves no SAML identity provider.', $exception);
        }

        return new Response($metadata->document, Response::HTTP_OK, [
            'Content-Type' => PublishedMetadata::CONTENT_TYPE,
        ] + $metadata->headers);
    }
}
