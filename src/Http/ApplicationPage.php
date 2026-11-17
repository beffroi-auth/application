<?php

declare(strict_types=1);

namespace App\Http;

use App\Account\ApplicationHoldings;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Consent\Consent;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplicationPage
{
    public function __construct(
        private ApplicationHoldings $holdings,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user, Consent $consent): Response
    {
        return $this->pages->render('account/application.html.twig', [
            'user' => $user,
            'holding' => $this->holdings->of($user, $consent),
        ]);
    }
}
