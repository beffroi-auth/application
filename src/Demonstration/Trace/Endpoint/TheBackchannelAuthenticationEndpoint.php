<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use App\Demonstration\Trace\NamedClient;
use App\Demonstration\Trace\RecordedEndpointInterface;
use Beffroi\Oidc\Application\Flow\TheUserIsAskedThroughABackchannel;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CIBA Core §7, §7.3
 */
final readonly class TheBackchannelAuthenticationEndpoint implements RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool
    {
        return ServedAt::is($request, $endpoints->backchannelAuthentication);
    }

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName
    {
        return OneFlowOfTheWiring::of($served->askingTheUserThroughABackchannel());
    }

    public function clientOf(Request $request): ?string
    {
        return NamedClient::of($request);
    }

    public function hops(): array
    {
        return [
            TheUserIsAskedThroughABackchannel::BACKCHANNEL_AUTHENTICATION_REQUEST,
            TheUserIsAskedThroughABackchannel::BACKCHANNEL_AUTHENTICATION_RESPONSE,
        ];
    }
}
