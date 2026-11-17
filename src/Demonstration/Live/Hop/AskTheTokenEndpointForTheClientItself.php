<?php

declare(strict_types=1);

namespace App\Demonstration\Live\Hop;

use App\Demonstration\Live\DemonstrationClient;
use App\Demonstration\Live\DrivenCall;
use App\Demonstration\Live\DrivenHopInterface;
use App\Demonstration\Live\Holdings;
use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Domain\Flow\TokenEndpointHops;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 6749 §4.4.2
 */
final readonly class AskTheTokenEndpointForTheClientItself implements DrivenHopInterface
{
    public function flows(ServedGrantsInterface $served): array
    {
        return $served->actingForTheClientItself();
    }

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): DrivenCall
    {
        $fields = [
            'grant_type' => $trace->grantType,
            'scope' => $client->scope,
        ];
        if ($client->resource !== null) {
            $fields['resource'] = $client->resource;
        }

        return new DrivenCall(Request::METHOD_POST, $endpoints->token, $fields);
    }

    public function hops(): array
    {
        return [TokenEndpointHops::REQUEST, TokenEndpointHops::RESPONSE];
    }

    public function keeps(array $answer): array
    {
        return Holdings::kept($answer, [Holdings::ACCESS_TOKEN, Holdings::REFRESH_TOKEN]);
    }
}
