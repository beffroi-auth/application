<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\InteractionPages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The check path of the passkey authenticator of the firewall, which answers before this controller.
 */
#[AsController]
final readonly class PasskeyLoginController
{
    public const string ROUTE = 'beffroi_interaction_passkey_login';

    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    #[Route('/login/passkey', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return $this->pages->redirectToPage(LoginController::ROUTE, $request);
    }
}
