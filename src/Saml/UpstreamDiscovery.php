<?php

declare(strict_types=1);

namespace App\Saml;

use App\Http\AuthorizationResponder;
use App\Http\FormTarget;
use Beffroi\Core\Application\Identity\FederatedDomains;
use Beffroi\Core\Domain\Identity\UpstreamProvider;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use Beffroi\Saml\Application\Upstream\UpstreamSignOn;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Sends an address of a federated domain to the sign-on of its provider, reading nothing of a person
 * (beffroi/saml S-61, S-62).
 */
final readonly class UpstreamDiscovery
{
    public function __construct(
        private FederatedDomains $federated,
        private FormTarget $formTargets,
        private UpstreamSignOnReferences $references,
        private ?UpstreamSignOn $signOn = null,
    ) {
    }

    /**
     * beffroi/saml S-61
     */
    public function asksForAnAddress(Tenant $tenant): bool
    {
        return $this->federated->federates($tenant);
    }

    /**
     * beffroi/saml S-62
     */
    public function hintedProvider(Tenant $tenant, Interaction $interaction): ?UpstreamProvider
    {
        $hint = $interaction->request->loginHint;
        if ($hint === null) {
            return null;
        }
        try {
            return $this->federated->providerOf($tenant, $hint);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * beffroi/saml S-51
     *
     * @return array<string, string>
     */
    public function signOn(SessionInterface $httpSession, ?Interaction $interaction, UpstreamProvider $provider, DateTimeImmutable $now): array
    {
        $parameters = [
            ServiceProviderRoutes::IDENTITY_PROVIDER_PARAMETER => $provider->name,
            ServiceProviderRoutes::REFERENCE_PARAMETER => $this->references->issue($httpSession, $interaction, $provider->name, $now),
        ];

        return $interaction === null ? $parameters : [AuthorizationResponder::INTERACTION_PARAMETER => $interaction->id, ...$parameters];
    }

    /**
     * beffroi/saml S-61
     *
     * Names in the "form-action" of the page of the address the origin of the single sign-on service of each
     * provider, where its submission ends over HTTP-Redirect.
     */
    public function mayReachTheProviders(Request $request, Tenant $tenant): void
    {
        foreach ($this->signOn?->destinations($tenant) ?? [] as $destination) {
            $this->formTargets->mayReach($request, SamlPostPage::originOf($destination));
        }
    }
}
