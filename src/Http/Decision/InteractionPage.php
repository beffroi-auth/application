<?php

declare(strict_types=1);

namespace App\Http\Decision;

use App\Http\AuthorizationResponder;
use App\Http\BrowserSession;
use App\Http\InteractionPages;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Remembers the interaction in the session of the browser and redirects it to one of its pages.
 */
final readonly class InteractionPage
{
    public function __construct(
        private InteractionPages $pages,
        private BrowserSession $browser,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function of(Request $request, Interaction $interaction, string $route): Response
    {
        $this->browser->begin($request->getSession(), $interaction);

        return $this->pages->redirect($this->router->generate($route, [
            AuthorizationResponder::INTERACTION_PARAMETER => $interaction->id,
        ]));
    }
}
