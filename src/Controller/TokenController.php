<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\DpopProofHeader;
use App\Http\ProtocolAnswer;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\Tls\PresentedClientCertificate;
use Beffroi\Oidc\Application\Token\TokenHandler;
use Beffroi\Oidc\Domain\Token\TokenResponse;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 6749 §3.2
 */
#[AsController]
final readonly class TokenController
{
    public function __construct(
        private TenantProvider $tenants,
        private TokenHandler $handler,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
    ) {
    }

    #[Route('/token', name: 'beffroi_protocol_token', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $response = $this->handler->handle(
                $this->tenants->provide(),
                $request->headers->get('Authorization'),
                RequestParameters::ofForm($request),
                $this->contexts->fromRequest($request),
                $this->certificates->ofRequest($request),
                DpopProofHeader::ofRequest($request),
            );
        } catch (OAuth2Exception $exception) {
            return ProtocolAnswer::error($exception);
        }

        return ProtocolAnswer::json($response->parameters(), Response::HTTP_OK, TokenResponse::CONTENT_TYPE, TokenResponse::HEADERS);
    }
}
