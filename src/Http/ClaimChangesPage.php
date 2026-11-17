<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\CanPageChangesOfAPersonInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClaimChangesPage
{
    public function __construct(
        private CanPageChangesOfAPersonInterface $changes,
        private AskedPage $page,
        private AskedPeriod $period,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user): Response
    {
        $period = $this->period->asked($user);

        return $this->pages->render('account/changes.html.twig', [
            'user' => $user,
            'changes' => $this->changes->pageOfChanges($user, $this->page->asked(), $period),
            'period' => $period,
        ]);
    }
}
