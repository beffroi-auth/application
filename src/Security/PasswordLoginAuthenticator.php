<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\LoginController;
use App\Doctrine\TenantProvider;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Authentication\AuthenticationFailedException;
use Beffroi\Core\Application\Authentication\PasswordVerification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use function trim;

final readonly class PasswordLoginAuthenticator implements AuthenticatorInterface, InteractiveAuthenticatorInterface, AuthenticationEntryPointInterface
{
    public const string USERNAME_PARAMETER = '_username';

    public const string PASSWORD_PARAMETER = '_password';

    public function __construct(
        private TenantProvider $tenants,
        private PasswordVerification $verification,
        private LoginFormToken $formToken,
        private RequestContextFactory $contexts,
        private InteractionLoginHandler $handler,
        private InteractionPages $pages,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $request->attributes->getString('_route') === LoginController::ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $username = trim($request->request->getString(self::USERNAME_PARAMETER));
        $password = $request->request->getString(self::PASSWORD_PARAMETER);
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);
        }
        $this->formToken->check($request);
        if ($username === '' || $password === '') {
            throw new BadCredentialsException('The login carries no username or no password.');
        }
        try {
            $credential = $this->verification->verify(
                $this->tenants->provide(),
                $username,
                $password,
                $this->contexts->fromRequest($request),
            );
        } catch (AuthenticationFailedException $exception) {
            throw new BadCredentialsException('The login was refused.', previous: $exception);
        }
        $user = new SecurityUser($credential->user, $credential);

        return new Passport(
            new UserBadge($credential->user->username, static fn (): SecurityUser => $user),
            new VerifiedPassword($password),
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
        return $this->handler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->handler->onAuthenticationFailure($request, $exception);
    }

    /**
     * A firewall of several authenticators must name an entry point; no page of this plane relies on it.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->pages->redirectToPage(LoginController::ROUTE, $request);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
