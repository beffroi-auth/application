<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\LoginController;
use App\Http\Account\BreachedPasswordDemand;
use App\Http\AuthorizationResponder;
use App\Http\InteractionPages;
use App\Http\LoginTarget;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * No target path is read from the body or a referer, only one this provider remembered in the session. A login
 * serving an authorization request is never sent to replace a breached password: a certification run plays one.
 */
final readonly class InteractionLoginHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    public function __construct(
        private InteractionPages $pages,
        private LoginTarget $loginTargets,
        private BreachedPasswordDemand $demands,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return $this->remembered($request, $token->getUser()) ?? $this->pages->redirectToPage(LoginController::ROUTE, $request);
    }

    /**
     * A refused login goes back to the login page and never to the page that asked for it, which would send the
     * browser here again and lose the message.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return $this->pages->redirectToPage(LoginController::ROUTE, $request);
    }

    /**
     * The page of the password comes first when a password of this person was found in a breach, whichever
     * credential signed them in: a password somebody else holds signs them in all the same.
     */
    private function remembered(Request $request, ?UserInterface $user): ?Response
    {
        if (! $request->hasSession() || $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER) !== '') {
            return null;
        }
        $httpSession = $request->getSession();
        $password = $user instanceof SecurityUser ? $user->passwordCredential() : null;

        return $this->demands->insteadOfResuming($httpSession, $password) ?? $this->loginTargets->resume($httpSession);
    }
}
