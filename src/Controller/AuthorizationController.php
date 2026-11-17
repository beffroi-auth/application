<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationRequestReaderInterface;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\CrossSitePost;
use App\Http\InteractionLocale;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Oidc\Application\Authorization\AuthorizationHandler;
use Beffroi\Oidc\Application\Authorization\AuthorizationRequestParser;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RFC 6749 §3.1, OIDC Core §3.1.2
 *
 * CrossSitePost goes before anything reads the request, so that a pushed request is not spent by the first post.
 */
#[AsController]
final readonly class AuthorizationController
{
    public function __construct(
        private TenantProvider $tenants,
        private AuthorizationRequestReaderInterface $requests,
        private AuthorizationRequestParser $parser,
        private AuthorizationHandler $handler,
        private BrowserSession $browser,
        private RequestContextFactory $contexts,
        private AuthorizationResponderInterface $responder,
        private InteractionPages $pages,
        private InteractionLocale $locale,
        private CrossSitePost $crossSitePost,
    ) {
    }

    #[Route('/authorize', name: 'beffroi_interaction_authorize', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $again = $this->crossSitePost->postAgainWithItsUiLocales($request);
        if ($again !== null) {
            return $again;
        }
        $tenant = $this->tenants->provide();
        try {
            $received = $this->requests->read($tenant, $request);
            $target = $this->parser->redirectTarget($tenant, $received->parameters, $received->pushed, $received->carriedByObject);
        } catch (OAuth2Exception $exception) {
            return $this->pages->error($exception->statusCode, $exception->error, $exception->errorDescription);
        }
        try {
            $authorizationRequest = $this->parser->parse($target, $received->parameters);
            $locale = $this->locale->choose($request, $authorizationRequest->uiLocales);
            $this->locale->apply($request, $locale);
            $decision = $this->handler->begin(
                $authorizationRequest,
                $this->browser->authenticationSession($request, $tenant),
                $this->contexts->fromRequest($request),
                $locale,
                $received->requestUriHash,
            );
        } catch (OAuth2Exception $exception) {
            return $this->responder->failure($request, $target, $exception);
        }

        return $this->responder->respond($request, $decision);
    }
}
