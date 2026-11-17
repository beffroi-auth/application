<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Application\Authorization\RequestObjectResolver;
use Beffroi\Oidc\Application\PushedAuthorization\PushedAuthorizationRequestResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 9126 §4, RFC 9101 §6.1
 */
final readonly class AuthorizationRequestReader implements AuthorizationRequestReaderInterface
{
    public function __construct(
        private PushedAuthorizationRequestResolver $pushedRequests,
        private RequestObjectResolver $requestObjects,
        private RequestContextFactory $contexts,
    ) {
    }

    public function read(Tenant $tenant, Request $request): ReceivedAuthorizationRequest
    {
        $parameters = RequestParameters::ofBrowserRequest($request);
        $pushed = $this->pushedRequests->resolve($tenant, $parameters, $this->contexts->fromRequest($request));
        $parameters = $pushed === null ? $parameters : PushedAuthorizationRequestResolver::parametersOf($pushed);
        $object = $this->requestObjects->resolve($tenant, $parameters);

        // The object of a pushed request was read where it was pushed: its record says whether one carried it.
        return new ReceivedAuthorizationRequest(
            $object->parameters ?? $parameters,
            $pushed !== null,
            $object !== null || ($pushed->carriedBySignedObject ?? false),
            $pushed?->requestUriHash,
        );
    }
}
