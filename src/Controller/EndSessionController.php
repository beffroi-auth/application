<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\BrowserSession;
use App\Http\CrossSitePost;
use App\Http\FormTarget;
use App\Http\InteractionLocale;
use App\Http\FormToken;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Http\RequestParameters;
use App\Saml\LogoutChainPages;
use Beffroi\Oidc\Application\EndSession\EndSessionHandler;
use Beffroi\Oidc\Application\EndSession\EndSessionRequestParser;
use Beffroi\Oidc\Domain\EndSession\LoggedOut;
use Beffroi\Oidc\Domain\EndSession\LogoutConfirmationRequired;
use Beffroi\Oidc\Exception\OAuth2Exception;
use function is_string;
use LogicException;
use function sprintf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RP-Initiated Logout §2, §4, Front-Channel Logout 1.0 §3
 */
#[AsController]
final readonly class EndSessionController
{
    public const string CSRF_TOKEN_ID = 'beffroi_logout';

    public const string CONFIRM_PARAMETER = 'confirm';

    public const string CONFIRM_YES = 'yes';

    public const array PARAMETERS = ['id_token_hint', 'client_id', 'post_logout_redirect_uri', 'state', 'logout_hint', 'ui_locales'];

    public function __construct(
        private TenantProvider $tenants,
        private EndSessionRequestParser $parser,
        private EndSessionHandler $handler,
        private BrowserSession $browser,
        private RequestContextFactory $contexts,
        private InteractionPages $pages,
        private FormToken $formTokens,
        private InteractionLocale $locale,
        private LogoutChainPages $logoutChain,
        private FormTarget $formTargets,
        private CrossSitePost $crossSitePost,
    ) {
    }

    #[Route('/logout', name: 'beffroi_interaction_logout', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $again = $this->crossSitePost->postAgainWithItsUiLocales($request);
        if ($again !== null) {
            return $again;
        }
        $tenant = $this->tenants->provide();
        $parameters = RequestParameters::ofBrowserRequest($request);
        try {
            $endSessionRequest = $this->parser->parse($tenant, $parameters);
        } catch (OAuth2Exception $exception) {
            return $this->pages->error($exception->statusCode, $exception->error, $exception->errorDescription);
        }
        $this->locale->apply($request, $this->locale->choose($request, $endSessionRequest->uiLocales));
        $session = $this->browser->authenticationSession($request, $tenant);
        $context = $this->contexts->fromRequest($request);

        $answer = $request->isMethod('POST') ? $parameters[self::CONFIRM_PARAMETER] ?? null : null;
        if ($answer === null) {
            $decision = $this->handler->begin($endSessionRequest, $session, $context);
        } else {
            $refusal = $this->formTokens->refusal($parameters[FormToken::PARAMETER] ?? null, self::CSRF_TOKEN_ID);
            if ($refusal !== null) {
                return $refusal;
            }
            if ($answer !== self::CONFIRM_YES) {
                return $this->pages->render('logged_out.html.twig', [
                    'logged_out' => false,
                    'client' => $endSessionRequest->client,
                ]);
            }
            $decision = $this->handler->confirm($endSessionRequest, $session, $context);
        }

        if ($decision instanceof LogoutConfirmationRequired) {
            $postLogoutRedirectUri = $decision->request->postLogoutRedirectUri;
            if ($postLogoutRedirectUri !== null) {
                $this->formTargets->mayEndAt($request, $postLogoutRedirectUri);
            }
            $this->logoutChain->mayEnd($request, $decision->session);

            return $this->pages->render('logout.html.twig', [
                'client' => $decision->request->client,
                'user' => $decision->session->user,
                'reason' => $decision->reason,
                'parameters' => self::carriedOver($parameters),
            ]);
        }
        if (! $decision instanceof LoggedOut) {
            throw new LogicException(sprintf('The decision "%s" is not one the end session endpoint knows.', $decision::class));
        }
        if ($decision->endedSession !== null) {
            $this->browser->unbind($request->getSession());
            $page = $this->logoutChain->begin($tenant, [$decision->endedSession], $decision->redirect?->toUri());
            if ($page !== null) {
                return $page;
            }
        }
        if ($decision->redirect !== null) {
            return $this->pages->redirect($decision->redirect->toUri());
        }

        return $this->pages->render('logged_out.html.twig', [
            'logged_out' => true,
            'client' => $decision->request->client,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, string>
     */
    private static function carriedOver(array $parameters): array
    {
        $carried = [];
        foreach (self::PARAMETERS as $name) {
            $value = $parameters[$name] ?? null;
            if (is_string($value) && $value !== '') {
                $carried[$name] = $value;
            }
        }

        return $carried;
    }
}
