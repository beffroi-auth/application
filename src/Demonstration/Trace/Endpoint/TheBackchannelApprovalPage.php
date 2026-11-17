<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use App\Demonstration\Trace\RecordedEndpointInterface;
use Beffroi\Oidc\Application\Flow\TheUserIsAskedThroughABackchannel;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TheBackchannelApprovalPage implements RecordedEndpointInterface
{
    public function recognises(Request $request, Response $response, ProviderEndpoints $endpoints): bool
    {
        return ServedAt::is($request, $endpoints->backchannelApproval) && $request->isMethod(Request::METHOD_POST);
    }

    public function flowOf(Request $request, ServedGrantsInterface $served): ?GrantTypeName
    {
        return OneFlowOfTheWiring::of($served->askingTheUserThroughABackchannel());
    }

    public function clientOf(Request $request): ?string
    {
        return null;
    }

    public function hops(): array
    {
        return [TheUserIsAskedThroughABackchannel::APPROVAL, null];
    }
}
