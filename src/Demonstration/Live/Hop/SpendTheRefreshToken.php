<?php

declare(strict_types=1);

namespace App\Demonstration\Live\Hop;

use App\Demonstration\Live\DemonstrationClient;
use App\Demonstration\Live\DrivenCall;
use App\Demonstration\Live\DrivenHopInterface;
use App\Demonstration\Live\Holdings;
use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Application\Flow\TheRefreshTokenIsSpent;
use Beffroi\Oidc\Port\ServedGrantsInterface;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use function count;
use Symfony\Component\HttpFoundation\Request;

/**
 * RFC 6749 §6
 */
final readonly class SpendTheRefreshToken implements DrivenHopInterface
{
    public function __construct(
        private ServedGrantsInterface $served,
    ) {
    }

    public function flows(ServedGrantsInterface $served): array
    {
        return $served->issuingRefreshTokens();
    }

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): ?DrivenCall
    {
        $refreshToken = $trace->holds(Holdings::REFRESH_TOKEN);
        $spending = $this->served->spendingRefreshTokens();
        if ($refreshToken === null || count($spending) !== 1) {
            return null;
        }

        return new DrivenCall(Request::METHOD_POST, $endpoints->token, [
            'grant_type' => $spending[0]->value,
            Holdings::REFRESH_TOKEN => $refreshToken,
        ]);
    }

    public function hops(): array
    {
        return [TheRefreshTokenIsSpent::REFRESH_REQUEST, TheRefreshTokenIsSpent::REFRESH_RESPONSE];
    }

    public function keeps(array $answer): array
    {
        return Holdings::kept($answer, [Holdings::ACCESS_TOKEN, Holdings::REFRESH_TOKEN]);
    }
}
