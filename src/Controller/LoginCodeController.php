<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\TenantProvider;
use App\Http\AuthorizationResponder;
use App\Http\AuthorizationResponderInterface;
use App\Http\BrowserSession;
use App\Http\FormTarget;
use App\Http\InteractionLocale;
use App\Http\InteractionPages;
use App\Http\LoginTarget;
use App\Http\PendingLoginStore;
use App\Http\RequestContextFactory;
use App\Security\CodeRefusal;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[AsController]
final readonly class LoginCodeController
{
    public const string ROUTE = 'beffroi_interaction_login_code';

    public function __construct(
        private TenantProvider $tenants,
        private BrowserSession $browser,
        private PendingLoginStore $pendingLogins,
        private RequestContextFactory $contexts,
        private AuthorizationResponderInterface $responder,
        private InteractionPages $pages,
        private AuthenticationUtils $authentication,
        private InteractionLocale $locale,
        private LoginTarget $loginTargets,
        private FormTarget $formTargets,
    ) {
    }

    #[Route('/login/code', name: self::ROUTE, methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $tenant = $this->tenants->provide();
        $interaction = $this->browser->pending($request, $tenant, $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER));
        if ($interaction === null && ! $this->loginTargets->isPending($request->getSession())) {
            return $this->responder->unknownInteraction();
        }
        if ($interaction !== null) {
            $this->locale->resume($request, $interaction);
            $this->formTargets->mayEndAt($request, $interaction->request->redirectUri);
        }
        $login = $this->pendingLogins->held($request->getSession());
        if ($login === null || $login->isExpired($this->contexts->fromRequest($request)->receivedAt)) {
            return $this->pages->redirectToPage(LoginController::ROUTE, $request);
        }

        return $this->pages->render('code.html.twig', [
            'interaction' => $interaction,
            'client' => $interaction?->request->client,
            'refusal' => CodeRefusal::shown($this->authentication->getLastAuthenticationError()),
        ]);
    }
}
