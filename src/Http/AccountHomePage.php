<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Account\AccountSections;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Symfony\Component\HttpFoundation\Response;

final readonly class AccountHomePage
{
    public function __construct(
        private AccountSections $sections,
        private InteractionPages $pages,
    ) {
    }

    public function render(AuthenticationSession $session): Response
    {
        return $this->pages->render('account/home.html.twig', [
            'user' => $session->user,
            'signed_in_since' => $session->establishedAt,
            'sections' => $this->sections->of($session->user),
        ]);
    }
}
