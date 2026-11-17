<?php

declare(strict_types=1);

namespace App\Profiler\Decision;

use App\Profiler\Observation;
use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;

interface DecisionObservationInterface
{
    /**
     * @return non-empty-string the decision this describes, as the decision names itself
     */
    public function decision(): string;

    public function observe(AuthorizationDecision $decision): Observation;
}
