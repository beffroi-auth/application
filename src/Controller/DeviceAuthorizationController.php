<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\ProtocolAnswer;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Http\Tls\PresentedClientCertificate;
use Beffroi\Oidc\Application\Device\DeviceAuthorizationHandler;
use Beffroi\Oidc\Domain\Device\DeviceAuthorizationResponse;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 8628 §3.1, §3.2
 */
#[AsController]
final readonly class DeviceAuthorizationController
{
    public const string ROUTE = 'beffroi_protocol_device_authorization';

    public function __construct(
        private TenantProvider $tenants,
        private DeviceAuthorizationHandler $handler,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
    ) {
    }

    #[Route('/device_authorization', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $response = $this->handler->handle(
                $this->tenants->provide(),
                $request->headers->get('Authorization'),
                RequestParameters::ofForm($request),
                $this->contexts->fromRequest($request),
                $this->certificates->ofRequest($request),
            );
        } catch (OAuth2Exception $exception) {
            return ProtocolAnswer::error($exception, DeviceAuthorizationResponse::HEADERS);
        }

        return ProtocolAnswer::json(
            $response->parameters(),
            DeviceAuthorizationResponse::STATUS_CODE,
            DeviceAuthorizationResponse::CONTENT_TYPE,
            DeviceAuthorizationResponse::HEADERS,
        );
    }
}
