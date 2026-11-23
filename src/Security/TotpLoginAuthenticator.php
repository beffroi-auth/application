<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\LoginCodeController;
use App\Doctrine\TenantProvider;
use App\Http\InteractionPages;
use App\Http\PendingLoginStore;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Authentication\AuthenticationFailedException;
use Beffroi\Core\Application\Authentication\PendingLoginVerification;
use Beffroi\Core\Application\Totp\TotpAuthentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final readonly class TotpLoginAuthenticator implements AuthenticatorInterface, InteractiveAuthenticatorInterface
{
    public const string CODE_PARAMETER = '_code';

    public function __construct(
        private TenantProvider $tenants,
        private PendingLoginStore $pendingLogins,
        private PendingLoginVerification $verification,
        private TotpAuthentication $authentication,
        private RequestContextFactory $contexts,
        private InteractionLoginHandler $handler,
        private InteractionPages $pages,
        private LoginFormToken $formToken,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $request->attributes->getString('_route') === LoginCodeController::ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $this->formToken->check($request);
        $code = $request->request->getString(self::CODE_PARAMETER);
        if ($code === '') {
            throw new AuthenticationException('The login carries no code.');
        }
        $context = $this->contexts->fromRequest($request);
        $login = $this->pendingLogins->held($request->getSession()) ?? throw new AuthenticationException('No login is pending for this browser.');
        try {
            $user = $this->verification->verify($this->tenants->provide(), $login, $context);
            $method = $this->authentication->authenticate($user, $code, $context);
        } catch (AuthenticationFailedException $exception) {
            throw new CodeRefusedException(CodeRefusal::of($code, $exception->reason), $exception);
        }

        return new Passport(
            new UserBadge($user->username),
            new TotpCredentials($login, $method),
        );
    }

    /**
     * The token of AbstractAuthenticator, which a readonly class cannot extend.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $this->pendingLogins->forget($request->getSession());

        return $this->handler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return $this->pages->redirectToPage(LoginCodeController::ROUTE, $request);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
