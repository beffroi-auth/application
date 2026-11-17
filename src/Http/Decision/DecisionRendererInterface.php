<?php

declare(strict_types=1);

namespace App\Http\Decision;

use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface DecisionRendererInterface
{
    /**
     * @return non-empty-string
     */
    public function decision(): string;

    public function render(Request $request, AuthorizationDecision $decision): Response;
}
