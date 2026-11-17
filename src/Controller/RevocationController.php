<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ProtocolAnswer;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\Tls\PresentedClientCertificate;
use Beffroi\Oidc\Application\Revocation\RevocationHandler;
use Beffroi\Oidc\Domain\Token\TokenResponse;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 7009 §2
 */
#[AsController]
final readonly class RevocationController
{
    public function __construct(
        private TenantProvider $tenants,
        private RevocationHandler $handler,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
    ) {
    }

    #[Route('/revoke', name: 'beffroi_protocol_revocation', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $this->handler->handle(
                $this->tenants->provide(),
                $request->headers->get('Authorization'),
                RequestParameters::ofForm($request),
                $this->contexts->fromRequest($request),
                $this->certificates->ofRequest($request),
            );
        } catch (OAuth2Exception $exception) {
            return ProtocolAnswer::error($exception);
        }

        return new Response('', Response::HTTP_OK, TokenResponse::HEADERS);
    }
}
