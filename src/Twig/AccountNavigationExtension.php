<?php

declare(strict_types=1);

namespace App\Twig;

use App\Account\AccountNavigation;
use Beffroi\Core\Domain\Identity\User;
use Twig\Attribute\AsTwigFunction;

final readonly class AccountNavigationExtension
{
    public function __construct(
        private AccountNavigation $navigation,
    ) {
    }

    /**
     * @return list<array{title: string|null, pages: list<array{route: string, title: string, icon: string|null, count: int|null}>}>
     */
    #[AsTwigFunction(name: 'account_navigation')]
    public function navigation(?User $user = null): array
    {
        return $this->navigation->groups($user);
    }

    /**
     * @return array<string, array{icon: string, description: string}>
     */
    #[AsTwigFunction(name: 'account_cards')]
    public function cards(): array
    {
        return $this->navigation->cards();
    }
}
