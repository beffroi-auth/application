<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\BrowserSession;
use App\Http\InteractionPages;
use Beffroi\Oidc\Application\Interaction\ThirdPartyInitiatedLoginHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OIDC Core §4
 */
#[AsController]
final readonly class ThirdPartyInitiatedLoginController
{
    public const string ROUTE = 'beffroi_interaction_initiate_login';

    public const string CLIENT_PARAMETER = 'client_id';

    public const string TARGET_PARAMETER = 'target_link_uri';

    /**
     * One answer for an unknown client and for one with no "initiate_login_uri": nobody learns which clients exist.
     */
    public const string NO_LOGIN_TO_BEGIN = 'no_login_to_begin';

    public function __construct(
        private TenantProvider $tenants,
        private ThirdPartyInitiatedLoginHandler $handler,
        private BrowserSession $browser,
        private InteractionPages $pages,
    ) {
    }

    #[Route('/initiate-login', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $session = $this->browser->authenticationSession($request, $tenant);
        $login = $this->handler->handle(
            $tenant,
            $request->query->getString(self::CLIENT_PARAMETER),
            $session?->user,
            $request->query->getString(self::TARGET_PARAMETER),
        );
        if ($login === null) {
            return $this->pages->error(Response::HTTP_NOT_FOUND, self::NO_LOGIN_TO_BEGIN);
        }

        return $this->pages->redirect($login->location());
    }
}
