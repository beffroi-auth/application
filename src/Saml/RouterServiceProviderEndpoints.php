<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Saml\Bridge\Model\ModelEntityId;
use Beffroi\Saml\Domain\Metadata\ServiceProviderEndpoints;
use Beffroi\Saml\Port\IdentityProviderEndpointsInterface;
use Beffroi\Saml\Port\ServiceProviderEndpointsInterface;
use LogicException;
use function sprintf;
use function strtr;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Locates the endpoints under the origin of the issuer, never the Host header of the request (beffroi/saml S-39).
 *
 * The entity ID is checked again at its first use: ServiceProviderEntityIdPass cannot check it when it or the
 * issuer holds an environment variable.
 */
final readonly class RouterServiceProviderEndpoints implements ServiceProviderEndpointsInterface
{
    /**
     * @param string|null $entityId null for the URL of the metadata
     */
    public function __construct(
        private UrlGeneratorInterface $router,
        private IdentityProviderEndpointsInterface $identityProvider,
        private ?string $entityId = null,
    ) {
    }

    public function locate(Tenant $tenant): ServiceProviderEndpoints
    {
        $metadata = $this->underTheIssuer(
            $tenant,
            ServiceProviderRoutes::METADATA,
            ServiceProviderRoutes::METADATA_PATH,
            'The SAML metadata of the service provider',
        );
        $endpoints = new ServiceProviderEndpoints(
            $this->entityId === null ? $metadata : ModelEntityId::read($this->entityId),
            $metadata,
            $this->underTheIssuer(
                $tenant,
                ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE,
                ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE_PATH,
                'The assertion consumer service of the service provider',
            ),
        );
        ServiceProviderEndpoints::assertOwnEntityId($endpoints->entityId, $this->identityProvider->locate($tenant)->metadata);

        return $endpoints;
    }

    /**
     * beffroi/saml S-65
     *
     * @throws LogicException when the routes of the provider are not mounted under the path of the issuer
     */
    public function metadataFor(Tenant $tenant, string $identityProvider): string
    {
        $segment = IdentityProviderSegment::of($identityProvider);

        return $this->underTheIssuer(
            $tenant,
            ServiceProviderRoutes::METADATA_FOR_IDENTITY_PROVIDER,
            strtr(ServiceProviderRoutes::METADATA_FOR_IDENTITY_PROVIDER_PATH, [
                '{identityProvider}' => $segment,
            ]),
            sprintf('The SAML metadata given to the identity provider "%s"', $identityProvider),
            [
                'identityProvider' => $segment,
            ],
        );
    }

    /**
     * @param string $path the expected path, its parameters written
     * @param array<string, string> $parameters
     *
     * @throws LogicException
     */
    private function underTheIssuer(Tenant $tenant, string $route, string $path, string $what, array $parameters = []): string
    {
        $generated = $tenant->issuer->origin() . $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
        $expected = $tenant->issuer->value . $path;
        if ($generated !== $expected) {
            throw new LogicException(sprintf(
                '%s is served at "%s" but the issuer "%s" puts it under "%s": mount the routes of the provider under the path of the issuer.',
                $what,
                $generated,
                $tenant->issuer->value,
                $expected,
            ));
        }

        return $generated;
    }
}
