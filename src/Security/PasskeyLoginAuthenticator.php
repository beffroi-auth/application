<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\PasskeyLoginController;
use App\Doctrine\TenantProvider;
use App\Http\PasskeyOptionsStore;
use Beffroi\Core\Application\Identity\FederatedDomains;
use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Beffroi\Core\Exception\PasskeyCeremonyFailedException;
use Beffroi\Core\Port\PasskeyAuthenticatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * A passkey of another tenant, a revoked one, a backup eligibility other than at enrolment and a person of a
 * federated domain are refused after the signature, so that every refusal costs the same.
 */
final readonly class PasskeyLoginAuthenticator implements AuthenticatorInterface, InteractiveAuthenticatorInterface
{
    public const string CSRF_TOKEN_ID = PasswordLoginAuthenticator::CSRF_TOKEN_ID;

    public const string CSRF_TOKEN_PARAMETER = '_csrf_token';

    public const string ASSERTION_PARAMETER = '_passkey';

    public function __construct(
        private TenantProvider $tenants,
        private PasskeyOptionsStore $options,
        private PasskeyAuthenticatorInterface $ceremony,
        private InteractionLoginHandler $handler,
        private FederatedDomains $federated,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $request->attributes->getString('_route') === PasskeyLoginController::ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $assertion = $request->request->getString(self::ASSERTION_PARAMETER);
        $token = $request->request->getString(self::CSRF_TOKEN_PARAMETER);
        if ($assertion === '') {
            throw new AuthenticationException('The login carries no assertion.');
        }
        $options = $this->options->takeRequest($request->getSession()) ?? throw new AuthenticationException('No passkey ceremony is pending for this browser.');
        $tenant = $this->tenants->provide();
        try {
            $verified = $this->ceremony->verifyAssertion($tenant, $options, $assertion);
        } catch (PasskeyCeremonyFailedException $exception) {
            throw new PasskeyLoginException(AuthenticationFailureReason::InvalidCredential, previous: $exception);
        }
        $credential = $verified->credential;
        $method = $credential->methods($verified->authenticatorData)[0];
        if (! $credential->tenant->equals($tenant)) {
            throw new PasskeyLoginException(AuthenticationFailureReason::InvalidCredential, $method);
        }
        if ($credential->backupEligibilityChanged($verified->authenticatorData) || $credential->signCountRegresses($verified->authenticatorData)) {
            throw new PasskeyLoginException(AuthenticationFailureReason::InvalidCredential, $method, $credential->user);
        }
        if ($credential->isRevoked()) {
            throw new PasskeyLoginException(AuthenticationFailureReason::RevokedCredential, $method, $credential->user);
        }
        // Refused whatever the page offered: a person of a federated domain signs in through their provider alone.
        if (! $this->federated->admitsLocalCredentials($credential->user)) {
            throw new PasskeyLoginException(AuthenticationFailureReason::FederatedDomain, $method, $credential->user);
        }

        return new Passport(
            new UserBadge($credential->user->username),
            new PasskeyCredentials($verified),
            [new CsrfTokenBadge(self::CSRF_TOKEN_ID, $token)],
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

    public function isInteractive(): bool
    {
        return true;
    }
}
