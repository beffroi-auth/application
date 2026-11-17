<?php

declare(strict_types=1);

namespace App\Http\Account;

use function array_values;
use Beffroi\Core\Domain\Identity\User;
use LogicException;
use function sprintf;

final readonly class AccountSections
{
    /**
     * @var list<AccountSectionInterface>
     */
    private array $sections;

    /**
     * @param iterable<AccountSectionInterface> $sections
     *
     * @throws LogicException when two sections name one route
     */
    public function __construct(iterable $sections)
    {
        $wired = [];
        foreach ($sections as $section) {
            $route = $section->route();
            if (isset($wired[$route])) {
                throw new LogicException(sprintf('Two sections of the account name the route "%s".', $route));
            }
            $wired[$route] = $section;
        }
        $this->sections = array_values($wired);
    }

    /**
     * @return list<array{route: string, title: string, state: AccountSectionState}>
     */
    public function of(User $user): array
    {
        $lines = [];
        foreach ($this->sections as $section) {
            $lines[] = [
                'route' => $section->route(),
                'title' => $section->title(),
                'state' => $section->state($user),
            ];
        }

        return $lines;
    }

    public function stateOf(string $route, User $user): ?AccountSectionState
    {
        foreach ($this->sections as $section) {
            if ($section->route() === $route) {
                return $section->state($user);
            }
        }

        return null;
    }

    /**
     * @return list<array{route: string, title: string}>
     */
    public function pages(): array
    {
        $pages = [];
        foreach ($this->sections as $section) {
            $pages[] = [
                'route' => $section->route(),
                'title' => $section->title(),
            ];
        }

        return $pages;
    }
}
