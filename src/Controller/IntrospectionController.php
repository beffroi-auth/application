<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ProtocolAnswer;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\Tls\PresentedClientCertificate;
use Beffroi\Oidc\Application\Introspection\IntrospectionHandler;
use Beffroi\Oidc\Domain\Introspection\IntrospectionResponse;
use Beffroi\Oidc\Exception\OAuth2Exception;
use function in_array;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7662 §2, RFC 9701 §4
 */
#[AsController]
final readonly class IntrospectionController
{
    public function __construct(
        private TenantProvider $tenants,
        private IntrospectionHandler $handler,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
    ) {
    }

    #[Route('/introspect', name: 'beffroi_protocol_introspection', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $response = $this->handler->handle(
                $this->tenants->provide(),
                $request->headers->get('Authorization'),
                RequestParameters::ofForm($request),
                $this->contexts->fromRequest($request),
                in_array(IntrospectionResponse::JWT_CONTENT_TYPE, $request->getAcceptableContentTypes(), true),
                $this->certificates->ofRequest($request),
            );
        } catch (OAuth2Exception $exception) {
            return ProtocolAnswer::error($exception);
        }

        return ProtocolAnswer::document($response->body(), Response::HTTP_OK, $response->contentType(), IntrospectionResponse::HEADERS);
    }
}
