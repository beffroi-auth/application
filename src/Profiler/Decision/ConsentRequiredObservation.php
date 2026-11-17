<?php

declare(strict_types=1);

namespace App\Profiler\Decision;

use App\Profiler\Observation;
use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use Beffroi\Oidc\Domain\Interaction\ConsentRequired;
use function implode;

final readonly class ConsentRequiredObservation implements DecisionObservationInterface
{
    public function decision(): string
    {
        return 'consent';
    }

    public function observe(AuthorizationDecision $decision): Observation
    {
        return new Observation('Decision', 'Decision', [
            'outcome' => 'the user is asked to consent',
            'why' => $decision instanceof ConsentRequired ? $decision->requirement->value : $decision->name(),
            'scopes asked for' => $decision instanceof ConsentRequired ? (string) $decision->scopes : '',
            'claims asked for by name' => $decision instanceof ConsentRequired ? implode(' ', $decision->claims->all) : '',
        ]);
    }
}
