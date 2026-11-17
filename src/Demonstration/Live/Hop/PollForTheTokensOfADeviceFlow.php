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
 * RFC 8628 §3.4
 */
final readonly class PollForTheTokensOfADeviceFlow implements DrivenHopInterface
{
    public function flows(ServedGrantsInterface $served): array
    {
        return $served->askingTheUserOnAnotherDevice();
    }

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): ?DrivenCall
    {
        $deviceCode = $trace->holds(Holdings::DEVICE_CODE);
        if ($deviceCode === null) {
            return null;
        }

        return new DrivenCall(Request::METHOD_POST, $endpoints->token, [
            'grant_type' => $trace->grantType,
            Holdings::DEVICE_CODE => $deviceCode,
        ]);
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
