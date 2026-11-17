<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use Beffroi\Oidc\Application\Discovery\JwksHandler;
use Beffroi\Oidc\Domain\Discovery\JwkSetResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7517 §5
 */
#[AsController]
final readonly class JwksController
{
    public function __construct(
        private TenantProvider $tenants,
        private JwksHandler $handler,
    ) {
    }

    #[Route('/jwks', name: 'beffroi_protocol_jwks', methods: ['GET'])]
    public function __invoke(): Response
    {
        $jwks = $this->handler->handle($this->tenants->provide());

        return new Response($jwks->toJson(), Response::HTTP_OK, [
            'Content-Type' => JwkSetResponse::CONTENT_TYPE,
        ] + $jwks->headers);
    }
}
