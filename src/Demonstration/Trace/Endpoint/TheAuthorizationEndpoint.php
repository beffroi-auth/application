<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use App\Demonstration\Trace\NamedClient;
use App\Demonstration\Trace\RecordedEndpointInterface;
use Beffroi\Oidc\Application\Flow\TheUserIsSentToTheClient;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 6749 §3.1
 */
final readonly class TheAuthorizationEndpoint implements RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool
    {
        return ServedAt::is($request, $endpoints->authorization);
    }

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName
    {
        return OneFlowOfTheWiring::of($served->sendingTheUserToTheClient());
    }

    public function clientOf(Request $request): ?string
    {
        return NamedClient::of($request);
    }

    public function hops(): array
    {
        // The answer is recorded where the browser leaves (TheBrowserSentBackToTheClient), which may be a login page.
        return [TheUserIsSentToTheClient::AUTHORIZATION_REQUEST, null];
    }
}
