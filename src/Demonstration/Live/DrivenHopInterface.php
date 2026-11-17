<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

use App\Demonstration\Trace\FlowTrace;
use Beffroi\Oidc\Domain\Client\GrantTypeName;
use Beffroi\Oidc\Domain\Discovery\ProviderEndpoints;
use Beffroi\Oidc\Port\ServedGrantsInterface;

interface DrivenHopInterface
{
    /**
     * @throws \Beffroi\Oidc\Exception\InvalidArgumentException
     *
     * @return list<GrantTypeName>
     */
    public function flows(ServedGrantsInterface $served): array;

    public function call(FlowTrace $trace, ProviderEndpoints $endpoints, DemonstrationClient $client): ?DrivenCall;

    /**
     * @return array{string, string} the hop the call fills, then the hop its answer fills
     */
    public function hops(): array;

    /**
     * @param array<array-key, mixed> $answer
     *
     * @return array<string, string>
     */
    public function keeps(array $answer): array;
}
