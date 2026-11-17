<?php

declare(strict_types=1);

namespace App\Demonstration\Live\Hop;

use App\Demonstration\Live\DemonstrationClient;
use App\Demonstration\Live\DrivenCall;
use App\Demonstration\Live\DrivenHopInterface;
use App\Demonstration\Live\Holdings;
use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Application\Flow\TheTokenIsGivenBack;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 7009 §2.1
 */
final readonly class GiveTheTokenBack implements DrivenHopInterface
{
    public function flows(ServedGrantsInterface $served): array
    {
        return $served->servedGrantTypes();
    }

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): ?DrivenCall
    {
        $refreshToken = $trace->holds(Holdings::REFRESH_TOKEN);
        $token = $refreshToken ?? $trace->holds(Holdings::ACCESS_TOKEN);
        if ($token === null) {
            return null;
        }

        return new DrivenCall(Request::METHOD_POST, $endpoints->revocation, [
            'token' => $token,
            'token_type_hint' => $refreshToken !== null ? Holdings::REFRESH_TOKEN : Holdings::ACCESS_TOKEN,
        ]);
    }

    public function hops(): array
    {
        return [TheTokenIsGivenBack::REVOCATION_REQUEST, TheTokenIsGivenBack::REVOCATION_RESPONSE];
    }

    public function keeps(array $answer): array
    {
        return [];
    }
}
