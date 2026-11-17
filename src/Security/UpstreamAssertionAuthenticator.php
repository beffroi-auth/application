<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\LoginController;
use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\CrossSitePost;
use App\Http\CrossSiteSessionCookie;
use App\Http\InteractionPages;
use App\Http\RequestContextFactory;
use App\Saml\RememberedSignOn;
use App\Saml\ServiceProviderRoutes;
use App\Saml\UpstreamLoginRequirement;
use App\Saml\UpstreamSignOnAnswer;
use App\Saml\UpstreamSignOns;
use Beffroi\Core\Application\Identity\UpstreamProvisioningRefusalReason;
use Beffroi\Core\Application\Identity\UpstreamProvisioningRefusedException;
use Beffroi\Core\Domain\Event\AuthenticationFailureReason;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationMethod;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Oidc\Domain\Authorization\Prompt;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Oidc\Domain\Interaction\LoginRequirement;
use Beffroi\Oidc\Exception\AccessDeniedException;
use Beffroi\Oidc\Exception\AccountSelectionRequiredException;
use Beffroi\Oidc\Exception\InteractionCompletedException;
use Beffroi\Oidc\Exception\InteractionExpiredException;
use Beffroi\Oidc\Exception\LoginRequiredException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Beffroi\Saml\Application\Upstream\AssertionConsumerService;
use Beffroi\Saml\Application\Upstream\UpstreamSignOn;
use Beffroi\Saml\Domain\Upstream\AuthnContextMethods;
use Beffroi\Saml\Domain\Upstream\UpstreamAssertion;
use Beffroi\Saml\Exception\AssertionRefusedException;
use Beffroi\Saml\Exception\UpstreamAuthenticationFailedException;
use Beffroi\Saml\Exception\UpstreamSignOnRefusedException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use function is_string;

/**
 * beffroi/saml spec/saml.md S-52 to S-59, section 6.14
 *
 * No form token: the request remembered in this browser's session under the RelayState binds the response to it.
 */
final readonly class UpstreamAssertionAuthenticator implements AuthenticatorInterface, InteractiveAuthenticatorInterface
{
    public const string REFUSED = 'saml_assertion_refused';

    private const string REMEMBERED = '_beffroi_upstream_sign_on';

    private const string RELAY_STATE = 'RelayState';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private UpstreamSignOns $signOns,
        private UpstreamLoginRequirement $requirement,
        private RequestContextFactory $contexts,
        private AuthorizationResponderInterface $responder,
        private InteractionPages $pages,
        private UpstreamSignOnAnswer $answers,
        private ClockInterface $clock,
        private ?AssertionConsumerService $consumer = null,
        private ?UpstreamSignOn $signOn = null,
    ) {
    }

    /**
     * A cross-site post that came without the session cookie is posted again by the route from this origin (S-28).
     */
    public function supports(Request $request): bool
    {
        return (
            $this->consumer instanceof AssertionConsumerService
            && $request->isMethod('POST')
            && $request->attributes->getString('_route') === ServiceProviderRoutes::ASSERTION_CONSUMER_SERVICE
            && CrossSitePost::isAForm($request)
            && ! CrossSiteSessionCookie::withheldFrom($request)
        );
    }

    public function authenticate(Request $request): Passport
    {
        $consumer = $this->consumer ?? throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        $tenant = $this->tenants->provide();
        $now = $this->clock->now();
        $relayState = $request->request->get(self::RELAY_STATE);
        $remembered = is_string($relayState) && $relayState !== '' ? $this->signOns->take($request->getSession(), $tenant, $relayState, $now) : null;
        if (! $remembered instanceof RememberedSignOn) {
            throw $this->refused(
                'The response answers no request this browser remembers (spec/saml.md S-53).',
                AuthenticationFailureReason::UpstreamResponseRefused,
            );
        }
        $interaction = $remembered->interaction === null ? null : $this->resumable($request, $tenant, $remembered->interaction, $now);
        try {
            $assertion = $consumer->receive($tenant, $request->request->all(), $remembered->identityProvider, $remembered->requestId);
        } catch (UpstreamAuthenticationFailedException $failure) {
            throw $this->failedAtTheProvider($request, $interaction, $failure);
        } catch (AssertionRefusedException $exception) {
            throw $this->refused($exception->getMessage(), AuthenticationFailureReason::UpstreamResponseRefused);
        } catch (KeyNotFoundException) {
            throw $this->refused(
                'A key of the identity provider retired while the response was read (spec/saml.md S-66).',
                AuthenticationFailureReason::UpstreamResponseRefused,
            );
        }
        // Before the person is written: past the firewall, nothing would catch the refusal of SessionOpener.
        if (! $tenant->loginFloor->isSatisfiedBy($assertion->methods)) {
            throw $this->refused(
                'The login of the identity provider proves less than this tenant requires of every login (spec/saml.md S-57).',
                AuthenticationFailureReason::BelowTheFloor,
            );
        }
        if ($assertion->sessionNotOnOrAfter !== null && $assertion->sessionNotOnOrAfter <= $now) {
            throw $this->refused('The session the identity provider allows is over (spec/saml.md S-57).', AuthenticationFailureReason::UpstreamResponseRefused);
        }
        $context = $this->contexts->fromRequest($request);
        try {
            $person = $consumer->personOf($tenant, $assertion, $context);
        } catch (UpstreamProvisioningRefusedException $exception) {
            throw $this->personRefused(
                $exception->reason === UpstreamProvisioningRefusalReason::PersonDisabled
                    ? AuthenticationFailureReason::DisabledUser
                    : AuthenticationFailureReason::UpstreamPersonRefused,
            );
        } catch (InvalidArgumentException) {
            throw $this->personRefused(AuthenticationFailureReason::UpstreamPersonRefused);
        }
        $held = $this->browser->authenticationSession($request, $tenant);
        if ($interaction instanceof Interaction) {
            $this->assertNothingMoreAsked($request, $tenant, $interaction, $remembered, $person, $assertion, $held, $context, $now);
        } else {
            $this->assertThePageIsAnswered($remembered, $person, $assertion, $held, $context, $now);
        }
        $request->attributes->set(self::REMEMBERED, $remembered);

        return new SelfValidatingPassport(
            new UserBadge($person->username, static fn (): SecurityUser => new SecurityUser($person, null)),
            [new UpstreamCredentials($assertion->methods, $assertion->authenticatedAt, $assertion->sessionNotOnOrAfter)],
        );
    }

    /**
     * The token of AbstractAuthenticator, which a readonly class cannot extend.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    /**
     * S-59: the target is the one remembered with the request, never one a response or a request carried.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $remembered = $request->attributes->get(self::REMEMBERED);
        if (! $remembered instanceof RememberedSignOn) {
            return $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED);
        }
        if ($remembered->page !== null) {
            return $this->pages->redirectToRoute($remembered->page->route, $remembered->page->parameters);
        }

        return $this->pages->redirectToRoute(LoginController::ROUTE, [
            AuthorizationResponder::INTERACTION_PARAMETER => (string) $remembered->interaction,
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof UpstreamLoginRefusedException) {
            return $exception->answer;
        }

        return $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED);
    }

    public function isInteractive(): bool
    {
        return true;
    }

    /**
     * S-53
     *
     * @throws UpstreamLoginRefusedException
     */
    private function resumable(Request $request, Tenant $tenant, string $id, DateTimeImmutable $now): Interaction
    {
        $interaction = $this->browser->pending($request, $tenant, $id);
        if (! $interaction instanceof Interaction) {
            throw new UpstreamLoginRefusedException($this->responder->unknownInteraction());
        }
        try {
            $interaction->assertResumable($now);
        } catch (InteractionExpiredException|InteractionCompletedException $exception) {
            throw new UpstreamLoginRefusedException($this->responder->unresumable($request, $interaction, $exception), previous: $exception);
        }

        return $interaction;
    }

    /**
     * S-58
     *
     * @throws UpstreamLoginRefusedException
     */
    private function assertNothingMoreAsked(
        Request $request,
        Tenant $tenant,
        Interaction $interaction,
        RememberedSignOn $remembered,
        User $person,
        UpstreamAssertion $assertion,
        ?AuthenticationSession $held,
        RequestContext $context,
        DateTimeImmutable $now,
    ): void {
        try {
            $asked = $this->requirement->stillAsked($interaction, $person, $assertion->methods, $assertion->authenticatedAt, $held, $context, $now);
            if ($asked === null) {
                return;
            }
            if ($remembered->forceAuthn) {
                throw $asked === LoginRequirement::AccountSelectionRequested
                    ? new AccountSelectionRequiredException('The identity provider answered for the person it holds a session of, ForceAuthn notwithstanding.')
                    : new LoginRequiredException('The identity provider did not authenticate the person as the request asks, ForceAuthn notwithstanding.');
            }
        } catch (OAuth2Exception $exception) {
            throw new UpstreamLoginRefusedException(
                $this->responder->failure($request, $interaction->request->redirectTarget(), $exception, $interaction),
                previous: $exception,
            );
        }
        $signOn = $this->signOn ?? throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        try {
            $sent = $signOn->start($tenant, $remembered->identityProvider, true);
        } catch (UpstreamSignOnRefusedException $exception) {
            throw $this->refused($exception->getMessage(), null);
        } catch (KeyNotFoundException $exception) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.', $exception);
        }
        $this->signOns->keep($request->getSession(), $tenant, $interaction, $sent);

        throw new UpstreamLoginRefusedException($this->answers->of($sent), 'The request is sent once more with ForceAuthn (spec/saml.md S-58).');
    }

    /**
     * S-59
     *
     * @throws UpstreamLoginRefusedException
     */
    private function assertThePageIsAnswered(
        RememberedSignOn $remembered,
        User $person,
        UpstreamAssertion $assertion,
        ?AuthenticationSession $held,
        RequestContext $context,
        DateTimeImmutable $now,
    ): void {
        if ($remembered->forceAuthn && $assertion->authenticatedAt < $remembered->issuedAt) {
            throw $this->refused(
                'The identity provider did not authenticate the person again, ForceAuthn notwithstanding (spec/saml.md S-59).',
                AuthenticationFailureReason::UpstreamResponseRefused,
                $person,
            );
        }
        $stepUp = $remembered->page?->stepUp;
        if ($stepUp !== null && ! $this->requirement->meets($stepUp, $person, $assertion->methods, $assertion->authenticatedAt, $held, $context, $now)) {
            throw $this->refused(
                'The identity provider proved none of the classes the page asks for (spec/saml.md S-59).',
                AuthenticationFailureReason::UpstreamResponseRefused,
                $person,
            );
        }
    }

    /**
     * S-58
     */
    private function failedAtTheProvider(
        Request $request,
        ?Interaction $interaction,
        UpstreamAuthenticationFailedException $failure,
    ): UpstreamLoginRefusedException {
        if (! $interaction instanceof Interaction) {
            return $this->refused($failure->getMessage(), null);
        }
        $answer = $failure->noPassive && $interaction->request->hasPrompt(Prompt::None)
            ? new LoginRequiredException('The identity provider authenticated nobody without asking the person.')
            : new AccessDeniedException('The identity provider authenticated nobody.');

        return new UpstreamLoginRefusedException(
            $this->responder->failure($request, $interaction->request->redirectTarget(), $answer, $interaction),
            $failure->getMessage(),
        );
    }

    /**
     * S-56: the page is the same whatever the reason the audit trail is told.
     */
    private function personRefused(AuthenticationFailureReason $reason): UpstreamLoginRefusedException
    {
        return $this->refused('The person the identity provider named is not one this provider signs in (spec/saml.md S-56).', $reason);
    }

    /**
     * No caught exception travels on: the message of the rule is all a refusal carries.
     */
    private function refused(string $description, ?AuthenticationFailureReason $reason, ?User $person = null): UpstreamLoginRefusedException
    {
        return new UpstreamLoginRefusedException(
            $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED, $description),
            $description,
            null,
            $reason === null ? null : new LoginFailure(new AuthenticationMethod(AuthnContextMethods::FEDERATED), $reason, null, $person),
        );
    }
}
