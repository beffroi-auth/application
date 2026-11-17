<?php

declare(strict_types=1);

namespace App\Profiler;

use App\Http\AuthorizationResponderInterface;
use App\Profiler\Decision\DecisionObservations;
use Beffroi\Oidc\Domain\Authorization\RedirectTarget;
use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Oidc\Exception\InteractionCompletedException;
use Beffroi\Oidc\Exception\InteractionExpiredException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProfilingAuthorizationResponder implements AuthorizationResponderInterface
{
    public function __construct(
        private AuthorizationResponderInterface $responder,
        private OpProfile $profile,
        private DecisionObservations $observations,
    ) {
    }

    public function respond(Request $request, AuthorizationDecision $decision): Response
    {
        $this->profile->record($this->observations->of($decision));

        return $this->responder->respond($request, $decision);
    }

    public function failure(Request $request, RedirectTarget $target, OAuth2Exception $exception, ?Interaction $interaction = null): Response
    {
        $this->profile->record(new Observation('Decision', 'Decision', [
            'outcome' => $exception->redirectable ? 'refused, and the client is told' : 'refused, and the user is shown a page',
            'error' => $exception->error,
            'description' => $exception->errorDescription,
            'response mode' => $target->responseMode,
        ]));

        return $this->responder->failure($request, $target, $exception, $interaction);
    }

    public function unresumable(Request $request, Interaction $interaction, InteractionExpiredException|InteractionCompletedException $exception): Response
    {
        $this->profile->record(new Observation('Decision', 'Decision', [
            'outcome' => 'the interaction cannot be resumed',
            'interaction' => $interaction->id,
        ]));

        return $this->responder->unresumable($request, $interaction, $exception);
    }

    public function unknownInteraction(): Response
    {
        $this->profile->record(new Observation('Decision', 'Decision', [
            'outcome' => 'a page was reached without an interaction this browser began',
        ]));

        return $this->responder->unknownInteraction();
    }
}
