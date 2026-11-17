<?php

declare(strict_types=1);

namespace App\Http;

use DateInterval;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sends to the login a session whose newest proof is older than the window, before a page lets somebody change what
 * signs them in: a session is no proof of presence. A page guards its POST as well as its GET.
 */
final readonly class RecentLogin
{
    public const string WITHIN = 'PT5M';

    public function __construct(
        private LoginTarget $loginTargets,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, string> $parameters written by the provider, never what a request carried
     */
    public function orSendToLogin(
        Request $request,
        AuthenticationSession $session,
        string $route,
        array $parameters = [],
    ): ?RedirectResponse {
        if ($this->isRecent($session)) {
            return null;
        }

        return $this->loginTargets->sendToLogin($request->getSession(), $route, $parameters);
    }

    public function isRecent(AuthenticationSession $session): bool
    {
        return $session->authenticatedAt()->add(new DateInterval(self::WITHIN)) > $this->clock->now();
    }
}
