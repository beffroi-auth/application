<?php

declare(strict_types=1);

namespace App\Routing;

use App\Controller\BackchannelAuthenticationController;
use App\Controller\ClientConfigurationReadController;
use App\Controller\ClientRegistrationController;
use App\Controller\DeviceAuthorizationController;
use App\Controller\DeviceVerificationController;
use App\Controller\PendingApprovalsController;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Domain\Discovery\ProviderMetadata;
use Beffroi\Oidc\Port\ClientConfigurationEndpointInterface;
use Beffroi\Oidc\Port\EndpointLocatorInterface;
use Beffroi\Oidc\Port\MutualTlsEndpointLocatorInterface;
use LogicException;
use function rtrim;
use function sprintf;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Locates the endpoints under the origin of the issuer, never the Host header of the request.
 */
final readonly class RouterEndpointLocator implements ClientConfigurationEndpointInterface, EndpointLocatorInterface, MutualTlsEndpointLocatorInterface
{
    /**
     * @param string|null $mutualTlsOrigin scheme and authority of the mutual TLS host, null or empty for none
     */
    public function __construct(
        private UrlGeneratorInterface $router,
        private ?string $mutualTlsOrigin = null,
    ) {
    }

    public function locate(Tenant $tenant): ProviderEndpoints
    {
        $this->assertTheRoutesAreUnderTheIssuer($tenant);

        return $this->endpointsUnder($tenant->issuer->origin());
    }

    /**
     * @throws LogicException
     */
    private function assertTheRoutesAreUnderTheIssuer(Tenant $tenant): void
    {
        $issuer = $tenant->issuer->value;
        $discovery = $tenant->issuer->origin() . $this->path('beffroi_protocol_discovery');
        $expected = $issuer . ProviderMetadata::OPENID_CONFIGURATION_PATH;
        if ($discovery !== $expected) {
            throw new LogicException(sprintf(
                'The discovery document is served at "%s" but the issuer "%s" makes clients look for it at "%s" (OIDC Discovery section 4.1): mount the routes of the provider under the path of the issuer.',
                $discovery,
                $issuer,
                $expected,
            ));
        }
    }

    /**
     * RFC 7592 §3
     */
    public function clientConfigurationFor(Tenant $tenant, string $clientId): string
    {
        $this->assertTheRoutesAreUnderTheIssuer($tenant);

        return (
            $tenant->issuer->origin()
            . $this->router->generate(
                ClientConfigurationReadController::ROUTE,
                [
                    ClientConfigurationReadController::CLIENT_ID => $clientId,
                ],
                UrlGeneratorInterface::ABSOLUTE_PATH,
            )
        );
    }

    /**
     * RFC 8705 §5
     */
    public function locateMutualTls(Tenant $tenant): ?ProviderEndpoints
    {
        $origin = rtrim($this->mutualTlsOrigin ?? '', '/');
        if ($origin === '') {
            return null;
        }

        return $this->endpointsUnder($origin);
    }

    private function endpointsUnder(string $origin): ProviderEndpoints
    {
        return new ProviderEndpoints(
            $origin . $this->path('beffroi_interaction_authorize'),
            $origin . $this->path('beffroi_protocol_token'),
            $origin . $this->path('beffroi_protocol_userinfo'),
            $origin . $this->path('beffroi_protocol_jwks'),
            $origin . $this->path('beffroi_interaction_logout'),
            $origin . $this->path('beffroi_protocol_introspection'),
            $origin . $this->path('beffroi_protocol_revocation'),
            $origin . $this->path('beffroi_protocol_pushed_authorization_request'),
            $origin . $this->path(DeviceAuthorizationController::ROUTE),
            $origin . $this->path(DeviceVerificationController::ROUTE),
            $origin . $this->path(BackchannelAuthenticationController::ROUTE),
            $origin . $this->path(PendingApprovalsController::ROUTE),
            $origin . $this->path(ClientRegistrationController::ROUTE),
        );
    }

    private function path(string $route): string
    {
        return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
