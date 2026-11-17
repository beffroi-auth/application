<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use App\Demonstration\Trace\RecordedEndpointInterface;
use App\Http\AuthorizationResponder;
use Beffroi\Oidc\Application\Flow\TheUserIsSentToTheClient;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 6749 §4.1.2
 *
 * Recognised by what it is and not by where it is served, which is whichever page the interaction ended on.
 */
final readonly class TheBrowserSentBackToTheClient implements RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool
    {
        if (! AnswerThatLeaves::is($response, $endpoints)) {
            return false;
        }
        if (ServedAt::is($request, $endpoints->authorization)) {
            return true;
        }

        foreach ([$request->query, $request->request] as $parameters) {
            if ($parameters->get(AuthorizationResponder::INTERACTION_PARAMETER) !== null) {
                return true;
            }
        }

        return false;
    }

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName
    {
        return OneFlowOfTheWiring::of($served->sendingTheUserToTheClient());
    }

    public function clientOf(Request $request): ?string
    {
        return null;
    }

    public function hops(): array
    {
        return [null, TheUserIsSentToTheClient::AUTHORIZATION_RESPONSE];
    }
}
