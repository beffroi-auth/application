<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Journal\ActsOfAnAccount;
use Beffroi\Core\Domain\Journal\JournalQuestion;
use Beffroi\Core\Port\CanPageActsOfAPersonInterface;
use Beffroi\Oidc\Domain\Administration\ReadsByAnAdministrator;
use Beffroi\Oidc\Domain\Consent\ConsentActs;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page of the acts of the signed-in person: what they did and what was done to them, the consents they gave or that
 * were taken back included, and the reads an administrator of their tenant made of them, those they made of themselves
 * left out.
 */
final readonly class ActsPage
{
    /**
     * @var list<string>
     */
    public const array ACTS = [...ActsOfAnAccount::NAMES, ...ConsentActs::NAMES, ...ReadsByAnAdministrator::NAMES];

    public function __construct(
        private CanPageActsOfAPersonInterface $acts,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user): Response
    {
        $period = $this->period->asked($user);

        return $this->pages->render('account/acts.html.twig', [
            'user' => $user,
            'acts' => $this->acts->pageOfActs(
                $user,
                new JournalQuestion(self::ACTS, period: $period, unlessTheirOwn: ReadsByAnAdministrator::NAMES),
                $this->asked->asked(),
            ),
            'period' => $period,
        ]);
    }
}
