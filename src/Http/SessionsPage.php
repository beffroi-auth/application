<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Port\CanPageActiveSessionsInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class SessionsPage
{
    public function __construct(
        private CanPageActiveSessionsInterface $sessions,
        private ClockInterface $clock,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
    ) {
    }

    public function render(AuthenticationSession $current): Response
    {
        $period = $this->period->asked($current->user);

        return $this->pages->render('account/sessions.html.twig', [
            'user' => $current->user,
            'sessions' => $this->sessions->pageOfActiveSessions($current->user, $this->clock->now(), $this->asked->asked(), $period),
            'period' => $period,
            'current' => $current->id->toString(),
        ]);
    }
}
