<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountPasswordController;
use App\Http\InteractionPages;
use App\Http\LoginTarget;
use Beffroi\Core\Domain\Credential\PasswordCredential;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Asked inside no authorization request: a page before its redirection would change what a certification run sees.
 */
final readonly class BreachedPasswordDemand
{
    public function __construct(
        private LoginTarget $loginTargets,
        private InteractionPages $pages,
    ) {
    }

    public function insteadOfResuming(SessionInterface $httpSession, ?PasswordCredential $password): ?RedirectResponse
    {
        return $this->isMade($httpSession, $password)
            ? $this->pages->redirectToRoute(AccountPasswordController::ROUTE)
            : null;
    }

    /**
     * Whether a login, and not the person, sent the browser to the page of the password.
     */
    public function isMade(SessionInterface $httpSession, ?PasswordCredential $password): bool
    {
        return $password !== null && $password->isBreached() && $this->loginTargets->isPending($httpSession);
    }

    public function answered(SessionInterface $httpSession): ?RedirectResponse
    {
        $resumed = $this->loginTargets->resume($httpSession);
        if ($resumed !== null) {
            $this->loginTargets->forget($httpSession);
        }

        return $resumed;
    }
}
