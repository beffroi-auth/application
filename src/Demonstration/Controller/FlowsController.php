<?php

declare(strict_types=1);

namespace App\Demonstration\Controller;

use App\Demonstration\Live\FlowDriver;
use App\Demonstration\Trace\TraceStore;
use Beffroi\Oidc\Port\FlowPicturesInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class FlowsController
{
    public const string ROUTE = 'app_demonstration_flows';

    public function __construct(
        private FlowPicturesInterface $pictures,
        private FlowDriver $driver,
        private TraceStore $traces,
        private Environment $twig,
    ) {
    }

    #[Route('', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(): Response
    {
        $flows = [];
        foreach ($this->pictures->pictures() as $picture) {
            $flows[] = [
                'picture' => $picture,
                'driven' => $this->driver->drives($picture->grantType),
                'clients' => $this->traces->clientsOf($picture->grantType->value),
            ];
        }

        return new Response($this->twig->render('demonstration/flows.html.twig', [
            'flows' => $flows,
        ]));
    }
}
