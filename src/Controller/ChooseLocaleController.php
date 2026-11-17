<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\BrowserSession;
use App\Http\BrowserPreferences;
use App\Http\FormToken;
use App\Http\RequestParameters;
use App\Message\RememberTheChosenLanguage;
use App\Locale\LocalePreference;
use App\Locale\ServedLocales;
use Beffroi\Oidc\Port\InteractionRepositoryInterface;
use function is_string;
use function preg_match;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ChooseLocaleController
{
    public const string ROUTE = 'beffroi_interaction_locale';

    public const string CSRF_TOKEN_ID = 'beffroi_locale';

    public const string PARAMETER = 'locale';

    public const string TARGET_PARAMETER = 'target';

    public function __construct(
        private MessageBusInterface $commandBus,
        private TenantProvider $tenants,
        private ServedLocales $served,
        private BrowserPreferences $preferences,
        private BrowserSession $browser,
        private InteractionRepositoryInterface $interactions,
        private FormToken $formTokens,
    ) {
    }

    #[Route('/locale', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $parameters = RequestParameters::ofBrowserRequest($request);
        $refusal = $this->formTokens->refusal($parameters[FormToken::PARAMETER] ?? null, self::CSRF_TOKEN_ID);
        if ($refusal !== null) {
            return $refusal;
        }
        $response = new RedirectResponse(self::backTo($parameters));
        $asked = $parameters[self::PARAMETER] ?? null;
        $locale = is_string($asked) ? $this->served->match([$asked]) : null;
        if ($locale === null) {
            return $response;
        }
        $response->headers->setCookie($this->preferences->cookie($request, LocalePreference::SLOT, $locale));
        $session = $this->browser->authenticationSession($request, $this->tenants->provide());
        if ($session !== null) {
            $this->commandBus->dispatch(new RememberTheChosenLanguage($session->user->id, $locale));
        }
        $id = $parameters[AuthorizationResponder::INTERACTION_PARAMETER] ?? null;
        $interaction = $this->browser->pending($request, $this->tenants->provide(), is_string($id) ? $id : null);
        if ($interaction !== null) {
            $interaction->readIn($locale);
            // Saved now: InteractionLocale::resume() would otherwise undo the choice at the next step.
            $this->interactions->save($interaction);
        }

        return $response;
    }

    /**
     * A path of this origin, or the login page: a choice of language never sends a browser elsewhere.
     *
     * @param array<string, mixed> $parameters
     */
    private static function backTo(array $parameters): string
    {
        $target = $parameters[self::TARGET_PARAMETER] ?? null;

        return is_string($target) && preg_match('#^/(?![/\\\\])[^\s\\\\]*$#D', $target) === 1 ? $target : '/';
    }
}
