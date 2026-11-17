<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use App\Statistics\InstanceStatistics;
use Beffroi\Oidc\Domain\Administration\ActingAdministrator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What the instance knows of itself now (decision 0040): the numbers of its tenant, its keys by state, its acts by name
 * over what its journal keeps, and what waits in each lane of its worker, as `beffroi:statistics` prints them.
 */
#[AsController]
final readonly class StatisticsController
{
    public function __construct(
        private InstanceStatistics $statistics,
    ) {
    }

    #[Route('/statistics', name: 'beffroi_administration_statistics', methods: ['GET'])]
    public function __invoke(ActingAdministrator $administrator): Response
    {
        $statistics = $this->statistics->of($administrator->tenant);
        $acts = [];
        foreach ($statistics->acts as $act => $count) {
            $acts[] = [
                'act' => $act,
                'count' => $count,
            ];
        }
        $queues = [];
        foreach ($statistics->backlogs as $transport => $messages) {
            $queues[] = [
                'transport' => $transport,
                'messages' => $messages,
            ];
        }

        return AdministrationAnswer::of([
            'since' => AdministrationAnswer::moment($statistics->since),
            'until' => AdministrationAnswer::moment($statistics->until),
            'users' => $statistics->users,
            'clients' => $statistics->clients,
            'open_sessions' => $statistics->openSessions,
            'keys' => $statistics->keys,
            'acts' => $acts,
            'queues' => $queues,
        ]);
    }
}
