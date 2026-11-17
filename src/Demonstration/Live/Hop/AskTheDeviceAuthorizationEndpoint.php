<?php

declare(strict_types=1);

namespace App\Demonstration\Live\Hop;

use App\Demonstration\Live\DemonstrationClient;
use App\Demonstration\Live\DrivenCall;
use App\Demonstration\Live\DrivenHopInterface;
use App\Demonstration\Live\Holdings;
use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Application\Flow\TheUserIsAskedOnAnotherDevice;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 8628 §3.1
 */
final readonly class AskTheDeviceAuthorizationEndpoint implements DrivenHopInterface
{
    public function flows(ServedGrantsInterface $served): array
    {
        return $served->askingTheUserOnAnotherDevice();
    }

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): DrivenCall
    {
        return new DrivenCall(Request::METHOD_POST, $endpoints->deviceAuthorization, [
            'scope' => $client->scope,
        ]);
    }

    public function hops(): array
    {
        return [
            TheUserIsAskedOnAnotherDevice::DEVICE_AUTHORIZATION_REQUEST,
            TheUserIsAskedOnAnotherDevice::DEVICE_AUTHORIZATION_RESPONSE,
        ];
    }

    public function keeps(array $answer): array
    {
        return Holdings::kept($answer, [Holdings::DEVICE_CODE, Holdings::USER_CODE, Holdings::VERIFICATION_URI]);
    }
}
