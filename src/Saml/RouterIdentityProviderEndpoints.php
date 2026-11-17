<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Saml\Domain\Metadata\IdentityProviderEndpoints;
use Beffroi\Saml\Port\IdentityProviderEndpointsInterface;
use LogicException;
use function sprintf;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Locates the endpoints under the origin of the issuer, never the Host header of the request (beffroi/saml S-4).
 */
final readonly class RouterIdentityProviderEndpoints implements IdentityProviderEndpointsInterface
{
    public function __construct(
        private UrlGeneratorInterface $router,
    ) {
    }

    public function locate(Tenant $tenant): IdentityProviderEndpoints
    {
        $issuer = $tenant->issuer->value;
        $metadata = $tenant->issuer->origin() . $this->router->generate(IdentityProviderRoutes::METADATA, [], UrlGeneratorInterface::ABSOLUTE_PATH);
        $expected = $issuer . IdentityProviderRoutes::METADATA_PATH;
        if ($metadata !== $expected) {
            throw new LogicException(sprintf(
                'The SAML metadata is served at "%s" but the issuer "%s" puts the identity provider under "%s": mount the routes of the provider under the path of the issuer.',
                $metadata,
                $issuer,
                $expected,
            ));
        }

        return new IdentityProviderEndpoints(
            $metadata,
            $tenant->issuer->origin() . $this->router->generate(IdentityProviderRoutes::SINGLE_SIGN_ON, [], UrlGeneratorInterface::ABSOLUTE_PATH),
            $tenant->issuer->origin() . $this->router->generate(IdentityProviderRoutes::SINGLE_LOGOUT, [], UrlGeneratorInterface::ABSOLUTE_PATH),
        );
    }
}
