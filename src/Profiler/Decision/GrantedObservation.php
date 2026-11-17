<?php

declare(strict_types=1);

namespace App\Profiler\Decision;

use function array_keys;
use App\Profiler\Observation;
use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use Beffroi\Oidc\Domain\Interaction\Granted;
use function implode;

/**
 * The names of the parameters of the answer and never their values: a code is a credential.
 */
final readonly class GrantedObservation implements DecisionObservationInterface
{
    public function decision(): string
    {
        return 'granted';
    }

    public function observe(AuthorizationDecision $decision): Observation
    {
        return new Observation('Decision', 'Decision', [
            'outcome' => 'granted',
            'response mode' => $decision->interaction->request->responseMode,
            'parameters sent back' => $decision instanceof Granted ? implode(' ', array_keys($decision->response->parameters())) : '',
        ]);
    }
}
