<?php

declare(strict_types=1);

namespace App\Http\Account;

use App\Controller\AccountActsController;
use function array_filter;
use Beffroi\Core\Domain\Event\AuthenticationFailed;
use Beffroi\Core\Domain\Event\UserAuthenticated;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Journal\JournalEntry;
use Beffroi\Core\Port\ActsOfAPersonInterface;
use function count;

final readonly class ActsSection implements AccountSectionInterface
{
    public function __construct(
        private ActsOfAPersonInterface $acts,
    ) {
    }

    public const int AMONG_THE_LAST = 20;

    /**
     * @var list<string>
     */
    private const array ATTEMPTS = [UserAuthenticated::NAME, AuthenticationFailed::NAME];

    public function route(): string
    {
        return AccountActsController::ROUTE;
    }

    public function title(): string
    {
        return 'acts.title';
    }

    public function state(User $user): AccountSectionState
    {
        $attempts = $this->acts->lastOf($user, self::ATTEMPTS, self::AMONG_THE_LAST);

        return new AccountSectionState('account.acts.state', [
            'count' => count(array_filter($attempts, static fn (JournalEntry $attempt): bool => $attempt->act === AuthenticationFailed::NAME)),
        ]);
    }
}
