<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\ClaimChangeQuestion;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\CanPageChangesOfAPersonInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClaimChangesPage
{
    public const string CLAIM = 'claim';

    public const string AUTHOR = 'by';

    public const string BY_THE_PERSON = 'you';

    public const string BY_AN_ADMINISTRATOR = 'administrator';

    public function __construct(
        private CanPageChangesOfAPersonInterface $changes,
        private AskedPage $page,
        private AskedPeriod $period,
        private AskedWord $word,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user): Response
    {
        $claims = $this->changes->claimsChangedOf($user);
        $question = new ClaimChangeQuestion(
            period: $this->period->asked($user),
            claim: $this->word->among(self::CLAIM, $claims),
            byAnAdministrator: $this->word->either(self::AUTHOR, self::BY_AN_ADMINISTRATOR, self::BY_THE_PERSON),
        );

        return $this->pages->render('account/changes.html.twig', [
            'user' => $user,
            'changes' => $this->changes->pageOfChanges($user, $question, $this->page->asked()),
            'question' => $question,
            'narrowed' => $question->period !== null || $question->claim !== null || $question->byAnAdministrator !== null,
            'claims' => $claims,
        ]);
    }
}
