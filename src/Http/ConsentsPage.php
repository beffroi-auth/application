<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Port\CanPageActiveConsentsInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ConsentsPage
{
    public function __construct(
        private CanPageActiveConsentsInterface $consents,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user): Response
    {
        $period = $this->period->asked($user);

        return $this->pages->render('account/consents.html.twig', [
            'user' => $user,
            'consents' => $this->consents->pageOfActiveConsents($user, $this->asked->asked(), $period),
            'period' => $period,
        ]);
    }
}
