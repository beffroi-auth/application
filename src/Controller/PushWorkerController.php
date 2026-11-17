<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProviderScript;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Push API §5
 *
 * Served at the root: a service worker controls only the paths under its own (Service Workers §3.1.1), and the page
 * a notification opens is elsewhere under that root.
 */
#[AsController]
final readonly class PushWorkerController
{
    public const string ROUTE = 'beffroi_interaction_push_worker';

    public const string FILE = 'push-worker.js';

    public function __construct(
        private ProviderScript $scripts,
    ) {
    }

    #[Route('/push-worker.js', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return $this->scripts->serve($request, self::FILE);
    }
}
