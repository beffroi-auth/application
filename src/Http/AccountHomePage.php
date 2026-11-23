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

    /**
     * @param bool $untoldChange whether to tell the person that a change to their credentials was not notified
     */
    public function render(AuthenticationSession $session, bool $untoldChange = false): Response
    {
        return $this->pages->render('account/home.html.twig', [
            'user' => $session->user,
            'signed_in_since' => $session->establishedAt,
            'sections' => $this->sections->of($session->user),
            'untold_change' => $untoldChange,
        ]);
    }
}
