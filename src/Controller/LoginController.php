<?php

declare(strict_types=1);

namespace App\Controller;

use function str_contains;
use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\FormTarget;
use App\Http\InteractionLocale;
use App\Http\InteractionPages;
use App\Http\OfferedLogins;
use App\Http\LoginTarget;
use App\Http\LoginAddresses;
use App\Http\WhatWasRefused;
use App\Saml\ServiceProviderRoutes;
use App\Saml\UpstreamDiscovery;
use App\Security\PasskeyLoginException;
use App\Http\RequestContextFactory;
use App\Http\StepUpOffer;
use Beffroi\Oidc\Application\Authorization\AuthorizationHandler;
use Beffroi\Oidc\Application\Hint\LoginHints;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Oidc\Domain\Interaction\LoginRequired;
use Beffroi\Oidc\Domain\Interaction\LoginRequirement;
use Beffroi\Oidc\Exception\InteractionCompletedException;
use Beffroi\Oidc\Exception\InteractionExpiredException;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[AsController]
final readonly class LoginController
{
    public const string ROUTE = 'beffroi_interaction_login';

    public function __construct(
        private TenantProvider $tenants,
        private AuthorizationHandler $handler,
        private BrowserSession $browser,
        private RequestContextFactory $contexts,
        private AuthorizationResponderInterface $responder,
        private InteractionPages $pages,
        private AuthenticationUtils $authentication,
        private InteractionLocale $locale,
        private LoginTarget $loginTargets,
        private LoginHints $hints,
        private FormTarget $formTargets,
        private OfferedLogins $offered,
        private UpstreamDiscovery $discovery,
        private LoginAddresses $addresses,
        private WhatWasRefused $refusals,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/login', name: self::ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $interaction = $this->browser->pending($request, $tenant, $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER));
        if ($interaction === null) {
            if (! $this->loginTargets->isPending($request->getSession())) {
                return $this->responder->unknownInteraction();
            }
            $stepUp = $this->loginTargets->pendingStepUp($request->getSession());
            $requirement = $stepUp === null ? null : LoginRequirement::StrongerFactorRequired;
            $asksForAnAddress = $this->discovery->asksForAnAddress($tenant);
            if ($asksForAnAddress && ! $this->addresses->passed($request->getSession(), null)) {
                return $this->address($request, $tenant, null, $requirement, $stepUp);
            }

            return $this->form($tenant, null, $requirement, $stepUp, $asksForAnAddress);
        }
        $this->locale->resume($request, $interaction);
        try {
            $decision = $this->handler->resume(
                $interaction,
                $this->browser->authenticationSession($request, $tenant),
                $this->contexts->fromRequest($request),
            );
        } catch (InteractionExpiredException|InteractionCompletedException $exception) {
            return $this->responder->unresumable($request, $interaction, $exception);
        } catch (OAuth2Exception $exception) {
            return $this->responder->failure($request, $interaction->request->redirectTarget(), $exception, $interaction);
        }
        if (! $decision instanceof LoginRequired) {
            return $this->responder->respond($request, $decision);
        }
        $this->formTargets->mayEndAt($request, $interaction->request->redirectUri);
        $stepUp = $decision->stepUp;
        $offer = $stepUp === null ? null : StepUpOffer::of($stepUp);
        $asksForAnAddress = $this->discovery->asksForAnAddress($tenant);
        if ($asksForAnAddress) {
            $hinted = $this->discovery->hintedProvider($tenant, $interaction);
            $hint = $interaction->request->loginHint;
            if ($hinted !== null && $hint !== null) {
                $now = $this->clock->now();
                $this->addresses->sendOn($request->getSession(), $interaction, $hint, $now);

                return $this->pages->redirectToRoute(ServiceProviderRoutes::UPSTREAM_SIGN_ON, $this->discovery->signOn(
                    $request->getSession(),
                    $interaction,
                    $hinted,
                    $now,
                ));
            }
            if (! $this->addresses->passed($request->getSession(), $interaction)) {
                return $this->address($request, $tenant, $interaction, $decision->requirement, $offer);
            }
        }

        return $this->form($tenant, $interaction, $decision->requirement, $offer, $asksForAnAddress);
    }

    /**
     * beffroi/saml S-61
     */
    private function address(Request $request, Tenant $tenant, ?Interaction $interaction, ?LoginRequirement $requirement, ?StepUpOffer $stepUp): Response
    {
        $this->discovery->mayReachTheProviders($request, $tenant);
        $lastUsername = $this->authentication->getLastUsername();
        // As the client wrote it and never resolved: a lookup would tell whether an account holds the hint.
        $hint = $interaction?->request->loginHint;
        $hinted = $hint !== null && str_contains($hint, '@') ? $hint : null;

        return $this->pages->render('login_address.html.twig', [
            'interaction' => $interaction,
            'client' => $interaction?->request->client,
            'requirement' => $requirement,
            'step_up' => $stepUp,
            'address' => $lastUsername === '' ? $hinted : $lastUsername,
            'refusal' => $this->refusals->take($request),
        ]);
    }

    /**
     * OIDC Core §3.1.2.1
     */
    private function form(Tenant $tenant, ?Interaction $interaction, ?LoginRequirement $requirement, ?StepUpOffer $stepUp, bool $afterTheAddress): Response
    {
        $lastUsername = $this->authentication->getLastUsername();
        $hinted = $interaction === null ? null : $this->hints->userNamedBy($interaction->request);
        $error = $this->authentication->getLastAuthenticationError();

        return $this->pages->render('login.html.twig', [
            'interaction' => $interaction,
            'client' => $interaction?->request->client,
            'requirement' => $requirement,
            'step_up' => $stepUp,
            'username' => $lastUsername === '' ? $hinted?->username : $lastUsername,
            'failed' => self::failed($error),
            'offered' => $this->offered->kinds($tenant),
            'after_the_address' => $afterTheAddress,
        ]);
    }

    /**
     * The login a refusal was of, so that a refused passkey is never told it typed a wrong password.
     */
    private static function failed(?AuthenticationException $error): ?string
    {
        if ($error === null) {
            return null;
        }

        return $error instanceof PasskeyLoginException ? 'passkey' : 'password';
    }
}
