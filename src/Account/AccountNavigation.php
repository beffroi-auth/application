<?php

declare(strict_types=1);

namespace App\Account;

use function array_map;
use function array_values;
use App\Controller\AccountArchiveController;
use App\Controller\AccountConsentsController;
use App\Controller\AccountNotificationsController;
use App\Controller\AccountPasskeysController;
use App\Controller\AccountPasswordController;
use App\Controller\AccountProfileController;
use App\Controller\AccountSessionsController;
use App\Controller\AccountActsController;
use App\Controller\AccountTwoFactorController;
use App\Controller\PendingApprovalsController;
use App\Http\Account\AccountSections;
use Beffroi\Core\Domain\Identity\User;
use function in_array;
use function is_int;

final readonly class AccountNavigation
{
    /**
     * An icon names a file of templates/layout/icon/.
     *
     * @var list<array{title: string|null, pages: array<string, array{icon: string, description: string}>}>
     */
    private const array HEADINGS = [
        [
            'title' => null,
            'pages' => [
                PendingApprovalsController::ROUTE => ['icon' => 'inbox', 'description' => 'navigation.description.approvals'],
            ],
        ],
        [
            'title' => 'navigation.group.record',
            'pages' => [
                AccountProfileController::ROUTE => ['icon' => 'card', 'description' => 'navigation.description.profile'],
                AccountArchiveController::ROUTE => ['icon' => 'archive', 'description' => 'navigation.description.archive'],
            ],
        ],
        [
            'title' => 'navigation.group.sign_in',
            'pages' => [
                AccountPasswordController::ROUTE => ['icon' => 'lock', 'description' => 'navigation.description.password'],
                AccountPasskeysController::ROUTE => ['icon' => 'key', 'description' => 'navigation.description.passkeys'],
                AccountTwoFactorController::ROUTE => ['icon' => 'shield', 'description' => 'navigation.description.two_factor'],
                AccountNotificationsController::ROUTE => ['icon' => 'bell', 'description' => 'navigation.description.notifications'],
            ],
        ],
        [
            'title' => 'navigation.group.activity',
            'pages' => [
                AccountSessionsController::ROUTE => ['icon' => 'monitor', 'description' => 'navigation.description.sessions'],
                AccountConsentsController::ROUTE => ['icon' => 'apps', 'description' => 'navigation.description.consents'],
                AccountActsController::ROUTE => ['icon' => 'history', 'description' => 'navigation.description.acts'],
            ],
        ],
    ];

    /**
     * @var list<string>
     */
    private const array COUNTED = [PendingApprovalsController::ROUTE];

    public function __construct(
        private AccountSections $sections,
    ) {
    }

    /**
     * @return list<array{title: string|null, pages: list<array{route: string, title: string, icon: string|null, count: int|null}>}>
     */
    public function groups(?User $user = null): array
    {
        $wired = [];
        foreach ($this->sections->pages() as $page) {
            $wired[$page['route']] = $page;
        }

        $groups = [];
        foreach (self::HEADINGS as $heading) {
            $held = [];
            foreach ($heading['pages'] as $route => $page) {
                if (isset($wired[$route])) {
                    $held[] = [...$wired[$route], 'icon' => $page['icon'], 'count' => $this->countOf($route, $user)];
                    unset($wired[$route]);
                }
            }
            if ($held !== []) {
                $groups[] = [
                    'title' => $heading['title'],
                    'pages' => $held,
                ];
            }
        }

        if ($wired !== []) {
            $groups[] = [
                'title' => null,
                'pages' => array_values(array_map(
                    static fn (array $page): array => [...$page, 'icon' => null, 'count' => null],
                    $wired,
                )),
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, array{icon: string, description: string}> by route
     */
    public function cards(): array
    {
        $cards = [];
        foreach (self::HEADINGS as $heading) {
            foreach ($heading['pages'] as $route => $page) {
                $cards[$route] = $page;
            }
        }

        return $cards;
    }

    private function countOf(string $route, ?User $user): ?int
    {
        if ($user === null || ! in_array($route, self::COUNTED, true)) {
            return null;
        }
        $count = $this->sections->stateOf($route, $user)?->parameters['count'] ?? null;

        return is_int($count) && $count > 0 ? $count : null;
    }
}
