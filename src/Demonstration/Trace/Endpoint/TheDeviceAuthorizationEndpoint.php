<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use App\Demonstration\Trace\NamedClient;
use App\Demonstration\Trace\RecordedEndpointInterface;
use Beffroi\Oidc\Application\Flow\TheUserIsAskedOnAnotherDevice;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 8628 §3.1
 */
final readonly class TheDeviceAuthorizationEndpoint implements RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool
    {
        return ServedAt::is($request, $endpoints->deviceAuthorization);
    }

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName
    {
        return OneFlowOfTheWiring::of($served->askingTheUserOnAnotherDevice());
    }

    public function clientOf(Request $request): ?string
    {
        return NamedClient::of($request);
    }

    public function hops(): array
    {
        return [
            TheUserIsAskedOnAnotherDevice::DEVICE_AUTHORIZATION_REQUEST,
            TheUserIsAskedOnAnotherDevice::DEVICE_AUTHORIZATION_RESPONSE,
        ];
    }
}
