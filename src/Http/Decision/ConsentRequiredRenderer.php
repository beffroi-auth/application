<?php

declare(strict_types=1);

namespace App\Http\Decision;

use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ConsentRequiredRenderer implements DecisionRendererInterface
{
    public const string ROUTE = 'beffroi_interaction_consent';

    public function __construct(
        private InteractionPage $page,
    ) {
    }

    public function decision(): string
    {
        return 'consent';
    }

    public function render(Request $request, AuthorizationDecision $decision): Response
    {
        return $this->page->of($request, $decision->interaction, self::ROUTE);
    }
}
