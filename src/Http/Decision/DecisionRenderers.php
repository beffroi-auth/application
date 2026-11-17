<?php

declare(strict_types=1);

namespace App\Http\Decision;

use Beffroi\Oidc\Domain\Interaction\AuthorizationDecision;
use LogicException;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class DecisionRenderers
{
    /**
     * @var array<string, DecisionRendererInterface> by decision
     */
    private array $renderers;

    /**
     * @param iterable<DecisionRendererInterface> $renderers
     *
     * @throws LogicException when two renderers answer for one decision
     */
    public function __construct(iterable $renderers)
    {
        $wired = [];
        foreach ($renderers as $renderer) {
            $decision = $renderer->decision();
            if (isset($wired[$decision])) {
                throw new LogicException(sprintf('Two renderers answer the decision "%s".', $decision));
            }
            $wired[$decision] = $renderer;
        }
        $this->renderers = $wired;
    }

    /**
     * @throws LogicException when nothing wired answers this decision
     */
    public function render(Request $request, AuthorizationDecision $decision): Response
    {
        $renderer = $this->renderers[$decision->name()] ?? throw new LogicException(sprintf(
            'The decision "%s" is not one this integration answers.',
            $decision->name(),
        ));

        return $renderer->render($request, $decision);
    }
}
