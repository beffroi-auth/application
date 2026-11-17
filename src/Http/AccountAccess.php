<?php

declare(strict_types=1);

namespace App\Http;

use App\Doctrine\TenantProvider;
use App\Plane\Plane;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AccountAccess
{
    public const string ROUTE_PREFIX = Plane::INTERACTION_ROUTE_PREFIX . 'account';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private FormToken $formTokens,
        private InteractionPages $pages,
    ) {
    }

    /**
     * A page that posts names its form token identifier: without one, no token is checked.
     */
    public function granted(Request $request, ?string $csrfTokenId = null): AuthenticationSession|Response
    {
        $session = $this->signedIn($request);
        if ($session === null) {
            return $this->pages->error(Response::HTTP_UNAUTHORIZED, InteractionPages::LOGIN_REQUIRED);
        }
        $refusal = $csrfTokenId === null
            ? null
            : $this->formTokens->refusal($request->request->getString(FormToken::PARAMETER), $csrfTokenId);
        if ($refusal !== null) {
            return $refusal;
        }

        return $session;
    }

    public function signedIn(Request $request): ?AuthenticationSession
    {
        return $this->browser->authenticationSession($request, $this->tenants->provide());
    }
}
