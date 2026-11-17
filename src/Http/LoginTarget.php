<?php

declare(strict_types=1);

namespace App\Http;

use App\Controller\LoginController;
use function array_is_list;
use function is_array;
use function is_string;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The page of this provider waiting for a login, kept in the HTTP session and never read from a request, so that
 * whoever fills a form sends the browser nowhere. The page forgets it once served, not the login: a password of a
 * user who holds a second factor is not a login yet.
 */
final readonly class LoginTarget
{
    public const string SESSION_KEY = '_beffroi_login_target';

    public function __construct(
        private InteractionPages $pages,
        private LoginAddresses $addresses = new LoginAddresses(),
    ) {
    }

    /**
     * @param array<string, string> $parameters written by the provider, never what a request carried
     */
    public function sendToLogin(
        SessionInterface $httpSession,
        string $route,
        array $parameters = [],
        ?StepUpOffer $stepUp = null,
    ): RedirectResponse {
        $httpSession->set(self::SESSION_KEY, [
            'route' => $route,
            'parameters' => $parameters,
            'step_up' => $stepUp === null
                ? null
                : [
                    'classes' => $stepUp->classes,
                    'kinds' => $stepUp->kinds,
                ],
        ]);
        // A login a page asks for begins at the step of the address, whatever was typed for another page's login.
        $this->addresses->forget($httpSession, null);

        return $this->pages->redirectToRoute(LoginController::ROUTE);
    }

    public function isPending(SessionInterface $httpSession): bool
    {
        return self::remembered($httpSession) !== null;
    }

    public function waiting(SessionInterface $httpSession): ?WaitingPage
    {
        $remembered = self::remembered($httpSession);
        if ($remembered === null) {
            return null;
        }

        return new WaitingPage($remembered['route'], $remembered['parameters'], $this->pendingStepUp($httpSession));
    }

    public function pendingStepUp(SessionInterface $httpSession): ?StepUpOffer
    {
        $remembered = self::remembered($httpSession);
        if ($remembered === null || $remembered['step_up'] === null) {
            return null;
        }

        return new StepUpOffer($remembered['step_up']['classes'], $remembered['step_up']['kinds']);
    }

    public function forget(SessionInterface $httpSession): void
    {
        $httpSession->remove(self::SESSION_KEY);
        $this->addresses->forget($httpSession, null);
    }

    public function forgetIfItIsWaitingFor(SessionInterface $httpSession, string $route): void
    {
        $remembered = self::remembered($httpSession);
        if ($remembered !== null && $remembered['route'] === $route) {
            $this->forget($httpSession);
        }
    }

    public function resume(SessionInterface $httpSession): ?RedirectResponse
    {
        $remembered = self::remembered($httpSession);

        return $remembered === null ? null : $this->pages->redirectToRoute($remembered['route'], $remembered['parameters']);
    }

    /**
     * @return array{route: string, parameters: array<string, string>, step_up: array{classes: non-empty-list<string>, kinds: non-empty-list<string>}|null}|null
     */
    private static function remembered(SessionInterface $httpSession): ?array
    {
        $remembered = $httpSession->get(self::SESSION_KEY);
        if (! is_array($remembered)) {
            return null;
        }
        $route = $remembered['route'] ?? null;
        $parameters = $remembered['parameters'] ?? null;
        if (! is_string($route) || $route === '' || ! is_array($parameters)) {
            return null;
        }
        $written = [];
        foreach ($parameters as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $written[$name] = $value;
            }
        }
        $stepUp = $remembered['step_up'] ?? null;
        $classes = is_array($stepUp) ? self::words($stepUp['classes'] ?? null) : [];
        $kinds = is_array($stepUp) ? self::words($stepUp['kinds'] ?? null) : [];

        return [
            'route' => $route,
            'parameters' => $written,
            'step_up' =>
                $classes === [] || $kinds === []
                    ? null
                    : [
                        'classes' => $classes,
                        'kinds' => $kinds,
                    ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function words(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $words = [];
        foreach ($value as $word) {
            if (! is_string($word) || $word === '') {
                return [];
            }
            $words[] = $word;
        }

        return $words;
    }
}
