<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\BrowserSession;
use App\Http\InteractionPages;
use App\Http\LoginTarget;
use App\Http\PasskeyOptionsStore;
use Beffroi\Core\Application\Passkey\PasskeyAuthentication;
use Beffroi\Core\Bridge\Webauthn\WebauthnCeremonies;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * WebAuthn Level 3 §5.5
 *
 * No user named and no allow list, so that the page reveals nothing about who has a passkey.
 */
#[AsController]
final readonly class PasskeyLoginOptionsController
{
    public const string ROUTE = 'beffroi_interaction_passkey_login_options';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private PasskeyAuthentication $authentication,
        private WebauthnCeremonies $ceremonies,
        private PasskeyOptionsStore $options,
        private LoginTarget $loginTargets,
    ) {
    }

    #[Route('/login/passkey/options', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $interaction = $this->browser->pending($request, $tenant, $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER));
        if ($interaction === null && ! $this->loginTargets->isPending($request->getSession())) {
            return new JsonResponse(
                [
                    'error' => AuthorizationResponder::UNKNOWN_INTERACTION,
                ],
                Response::HTTP_BAD_REQUEST,
                InteractionPages::HEADERS,
            );
        }
        $options = $this->authentication->requestOptions($tenant);
        $this->options->storeRequest($request->getSession(), $options);

        return new Response($this->ceremonies->optionsToJson($options), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
            ...InteractionPages::HEADERS,
        ]);
    }
}
