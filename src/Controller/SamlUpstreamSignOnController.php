<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\InteractionPages;
use App\Http\LoginAddresses;
use App\Http\LoginTarget;
use App\Saml\UpstreamLoginRequirement;
use App\Saml\UpstreamSignOnAnswer;
use App\Saml\ServiceProviderRoutes;
use App\Saml\UpstreamSignOnReferences;
use App\Saml\UpstreamSignOns;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Oidc\Exception\InteractionCompletedException;
use Beffroi\Oidc\Exception\InteractionExpiredException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Beffroi\Saml\Application\Upstream\UpstreamSignOn;
use Beffroi\Saml\Exception\UpstreamSignOnRefusedException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md S-51, section 6.11
 *
 * Starts only under the one-time reference the login page drew in the session: a page of another site can send the
 * browser here, but starts no login (login CSRF).
 */
#[AsController]
final readonly class SamlUpstreamSignOnController
{
    public const string REFUSED = 'saml_upstream_sign_on_refused';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private AuthorizationResponderInterface $responder,
        private UpstreamSignOns $signOns,
        private UpstreamSignOnAnswer $answers,
        private InteractionPages $pages,
        private ClockInterface $clock,
        private LoginTarget $loginTargets,
        private UpstreamLoginRequirement $requirement,
        private LoginAddresses $addresses,
        private UpstreamSignOnReferences $references,
        private ?UpstreamSignOn $signOn = null,
    ) {
    }

    #[Route(ServiceProviderRoutes::UPSTREAM_SIGN_ON_PATH, name: ServiceProviderRoutes::UPSTREAM_SIGN_ON, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if (! $this->signOn instanceof UpstreamSignOn) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.');
        }
        $tenant = $this->tenants->provide();
        $session = $this->browser->authenticationSession($request, $tenant);
        $now = $this->clock->now();
        $identityProvider = $request->query->getString(ServiceProviderRoutes::IDENTITY_PROVIDER_PARAMETER);
        $reference = $request->query->getString(ServiceProviderRoutes::REFERENCE_PARAMETER);
        $id = $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER);
        if ($id === '') {
            $for = $this->loginTargets->waiting($request->getSession());
            if ($for === null) {
                return $this->responder->unknownInteraction();
            }
            if (! $this->references->take($request->getSession(), null, $identityProvider, $reference, $now)) {
                return $this->unrequested();
            }
            $forceAuthn = $this->requirement->forcesALogin(null, $session, $now);
            $address = $this->addresses->takeSent($request->getSession(), null, $now);
        } else {
            $for = $this->browser->pending($request, $tenant, $id);
            if ($for === null) {
                return $this->responder->unknownInteraction();
            }
            try {
                $for->assertResumable($now);
            } catch (InteractionExpiredException|InteractionCompletedException $exception) {
                return $this->responder->unresumable($request, $for, $exception);
            }
            if (! $this->references->take($request->getSession(), $for, $identityProvider, $reference, $now)) {
                return $this->unrequested();
            }
            try {
                $forceAuthn = $this->requirement->forcesALogin($for, $session, $now);
            } catch (OAuth2Exception $exception) {
                return $this->responder->failure($request, $for->request->redirectTarget(), $exception, $for);
            }
            $address = $this->addresses->takeSent($request->getSession(), $for, $now);
        }
        try {
            $sent = $this->signOn->start($tenant, $identityProvider, $forceAuthn, $address);
        } catch (UpstreamSignOnRefusedException $exception) {
            return $this->pages->error(Response::HTTP_BAD_REQUEST, self::REFUSED, $exception->getMessage());
        } catch (KeyNotFoundException $exception) {
            throw new NotFoundHttpException('This tenant serves no SAML service provider.', $exception);
        }
        $this->signOns->keep($request->getSession(), $tenant, $for, $sent);

        return $this->answers->of($sent);
    }

    private function unrequested(): Response
    {
        return $this->pages->error(
            Response::HTTP_BAD_REQUEST,
            self::REFUSED,
            'The sign-on starts from the login page of this browser, under the reference it drew (spec/saml.md S-51).',
        );
    }
}
