<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\DpopProofHeader;
use App\Http\ProtectedResourceRefusals;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\ServesAProtectedResourceInterface;
use App\Http\Tls\PresentedClientCertificate;
use Beffroi\Oidc\Application\UserInfo\UserInfoHandler;
use Beffroi\Oidc\Domain\UserInfo\UserInfoResponse;
use Beffroi\Oidc\Exception\MissingBearerTokenException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OIDC Core §5.3
 *
 * A GET hands the module no parameter at all, the query included (RFC 6750 §2.3).
 */
#[AsController]
final readonly class UserInfoController implements ServesAProtectedResourceInterface
{
    public function __construct(
        private TenantProvider $tenants,
        private UserInfoHandler $handler,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
        private ProtectedResourceRefusals $refusals,
    ) {
    }

    #[Route('/userinfo', name: 'beffroi_protocol_userinfo', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $response = $this->handler->handle(
                $this->tenants->provide(),
                $request->getMethod(),
                $request->headers->get('Authorization'),
                $request->isMethod('POST') ? RequestParameters::ofForm($request) : [],
                $this->contexts->fromRequest($request),
                $this->certificates->ofRequest($request),
                DpopProofHeader::ofRequest($request),
            );
        } catch (OAuth2Exception|MissingBearerTokenException $exception) {
            return $this->refusals->of($exception);
        }

        return new Response($response->body, Response::HTTP_OK, [
            'Content-Type' => $response->contentType,
        ] + UserInfoResponse::HEADERS);
    }
}
