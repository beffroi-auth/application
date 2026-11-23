<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Application\Session\ProvedMethods;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Domain\Session\SessionQuestion;
use Beffroi\Core\Port\CanPageActiveSessionsInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class SessionsPage
{
    public const string SEEN_FROM = 'seen_from';

    public const string SEEN_TO = 'seen_to';

    public const string PROOF = 'proof';

    public function __construct(
        private CanPageActiveSessionsInterface $sessions,
        private ProvedMethods $methods,
        private ClockInterface $clock,
        private AskedPage $asked,
        private AskedPeriod $period,
        private AskedWord $word,
        private InteractionPages $pages,
    ) {
    }

    public function render(AuthenticationSession $current): Response
    {
        $user = $current->user;
        $now = $this->clock->now();
        $methods = $this->methods->of($user, $now);
        $question = new SessionQuestion(
            opened: $this->period->asked($user),
            seen: $this->period->asked($user, self::SEEN_FROM, self::SEEN_TO),
            provedWith: $this->word->among(self::PROOF, $methods),
        );

        return $this->pages->render('account/sessions.html.twig', [
            'user' => $user,
            'sessions' => $this->sessions->pageOfActiveSessions($user, $now, $question, $this->asked->asked()),
            'question' => $question,
            'narrowed' => $question->opened !== null || $question->seen !== null || $question->provedWith !== null,
            'methods' => $methods,
            'current' => $current->id->toString(),
        ]);
    }
}
