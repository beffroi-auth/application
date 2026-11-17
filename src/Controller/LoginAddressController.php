<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\FormToken;
use App\Http\InteractionPages;
use App\Http\LoginAddresses;
use App\Http\LoginTarget;
use App\Http\WhatWasRefused;
use App\Saml\ServiceProviderRoutes;
use App\Saml\UpstreamDiscovery;
use Beffroi\Core\Application\Identity\FederatedDomains;
use Beffroi\Core\Exception\InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use function trim;

/**
 * beffroi/saml S-61
 *
 * Nothing here reads a person: the answer and its timing are the same whether somebody holds the address or not.
 */
#[AsController]
final readonly class LoginAddressController
{
    public const string ROUTE = 'beffroi_interaction_login_address';

    public const string CSRF_TOKEN_ID = 'beffroi_login_address';

    public const string ADDRESS_PARAMETER = '_address';

    public const string UNREADABLE = 'address_unreadable';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private AuthorizationResponderInterface $responder,
        private FormToken $formTokens,
        private FederatedDomains $federated,
        private LoginAddresses $addresses,
        private WhatWasRefused $refusals,
        private InteractionPages $pages,
        private LoginTarget $loginTargets,
        private ClockInterface $clock,
        private UpstreamDiscovery $discovery,
    ) {
    }

    #[Route('/login/address', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $id = $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER);
        $interaction = $id === '' ? null : $this->browser->pending($request, $tenant, $id);
        if ($interaction === null && ($id !== '' || ! $this->loginTargets->isPending($request->getSession()))) {
            return $this->responder->unknownInteraction();
        }
        $refusal = $this->formTokens->refusal($request->request->get(FormToken::PARAMETER), self::CSRF_TOKEN_ID);
        if ($refusal !== null) {
            return $refusal;
        }
        $back = $interaction === null
            ? []
            : [
                AuthorizationResponder::INTERACTION_PARAMETER => $interaction->id,
            ];
        $address = trim($request->request->getString(self::ADDRESS_PARAMETER));
        if ($address === '') {
            $this->addresses->forget($request->getSession(), $interaction);

            return $this->pages->afterTheForm(LoginController::ROUTE, $back);
        }
        try {
            $provider = $this->federated->providerOf($tenant, $address);
        } catch (InvalidArgumentException) {
            $this->refusals->keep($request, self::UNREADABLE, self::ADDRESS_PARAMETER);

            return $this->pages->afterTheForm(LoginController::ROUTE, $back);
        }
        if ($provider !== null) {
            $now = $this->clock->now();
            $this->addresses->sendOn($request->getSession(), $interaction, $address, $now);

            return $this->pages->afterTheForm(ServiceProviderRoutes::UPSTREAM_SIGN_ON, $this->discovery->signOn(
                $request->getSession(),
                $interaction,
                $provider,
                $now,
            ));
        }
        $this->addresses->pass($request->getSession(), $interaction, $address);

        return $this->pages->afterTheForm(LoginController::ROUTE, $back);
    }
}
