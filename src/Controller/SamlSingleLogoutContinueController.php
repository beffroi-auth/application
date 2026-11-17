<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\InteractionPages;
use App\Saml\IdentityProviderRoutes;
use App\Saml\LogoutChainCookie;
use App\Saml\LogoutChainPages;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Saml\Application\SingleLogout\ProviderLogout;
use Beffroi\Saml\Exception\LogoutRefusedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * spec/saml.md S-32, S-35, section 6.6 step 2
 */
#[AsController]
final readonly class SamlSingleLogoutContinueController
{
    public function __construct(
        private TenantProvider $tenants,
        private ProviderLogout $logout,
        private LogoutChainPages $chain,
        private InteractionPages $pages,
        private LogoutChainCookie $cookie,
    ) {
    }

    #[Route(IdentityProviderRoutes::SINGLE_LOGOUT_CONTINUE_PATH, name: IdentityProviderRoutes::SINGLE_LOGOUT_CONTINUE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $secret = $this->cookie->presented($request);
        try {
            return $this->chain->step($this->logout->resume($tenant, $request->query->getString(IdentityProviderRoutes::CHAIN_PARAMETER), $secret), $secret);
        } catch (LogoutRefusedException $exception) {
            return $this->pages->error(Response::HTTP_BAD_REQUEST, SamlSingleLogoutController::REFUSED, $exception->getMessage());
        } catch (KeyNotFoundException) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, SamlSingleLogoutController::REFUSED, 'This tenant serves no SAML identity provider.');
        }
    }
}
