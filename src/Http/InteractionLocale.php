<?php

declare(strict_types=1);

namespace App\Http;

use function array_values;
use App\Locale\LocalePreference;
use App\Locale\ServedLocales;
use App\Locale\UserLocale;
use App\Plane\Plane;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Core\Port\AmbientTenantInterface;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use function str_starts_with;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class InteractionLocale
{
    /**
     * Above the LocaleListener of the kernel (16), below the router (32).
     */
    public const int PRIORITY = 17;

    public const string OF_THE_ACCOUNT = '_beffroi_locale_of_the_account';

    public function __construct(
        private LocaleSwitcher $switcher,
        private ServedLocales $served,
        private BrowserPreferences $preferences,
        private BrowserSession $browser,
        private AmbientTenantInterface $tenants,
        private UserLocale $persons,
    ) {
    }

    /**
     * What the visitor chose comes before "ui_locales", which OIDC Core §3.1.2.1 makes a hint of the client.
     *
     * @param list<string> $uiLocales most preferred first
     */
    public function choose(Request $request, array $uiLocales = []): string
    {
        $theirs = $this->ofTheAccount($request);
        if ($theirs !== null) {
            $request->attributes->set(self::OF_THE_ACCOUNT, true);

            return $theirs;
        }

        return (
            $this->preferences->read($request, LocalePreference::SLOT)
            ?? $this->served->match($uiLocales)
            ?? $this->served->match(array_values($request->getLanguages()))
            ?? $this->served->default
        );
    }

    private function ofTheAccount(Request $request): ?string
    {
        try {
            $session = $this->browser->authenticationSession($request, $this->tenants->provide());
        } catch (RuntimeException) {
            return null;
        }

        return $session === null ? null : $this->persons->claimedBy($session->user);
    }

    public function apply(Request $request, string $locale): void
    {
        $request->setLocale($locale);
        $this->switcher->setLocale($locale);
    }

    public function resume(Request $request, Interaction $interaction): void
    {
        if ($interaction->locale !== null) {
            $this->apply($request, $interaction->locale);
        }
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (! $event->isMainRequest() || ! str_starts_with($route, Plane::INTERACTION_ROUTE_PREFIX)) {
            return;
        }
        $request->attributes->set('_locale', $this->choose($request));
    }
}
