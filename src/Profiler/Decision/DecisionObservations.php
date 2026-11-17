<?php

declare(strict_types=1);

namespace App\Profiler\Decision;

use App\Profiler\Observation;
use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;

final readonly class DecisionObservations
{
    /**
     * @var array<string, DecisionObservationInterface>
     */
    private array $observations;

    /**
     * @param iterable<DecisionObservationInterface> $observations
     */
    public function __construct(iterable $observations)
    {
        $wired = [];
        foreach ($observations as $observation) {
            $wired[$observation->decision()] = $observation;
        }
        $this->observations = $wired;
    }

    public function of(AuthorizationDecision $decision): Observation
    {
        $observation = $this->observations[$decision->name()] ?? null;

        return $observation?->observe($decision) ?? new Observation('Decision', 'Decision', [
            'outcome' => $decision->name(),
        ]);
    }
}
