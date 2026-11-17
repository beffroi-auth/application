<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProviderScript;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Push API §3.4
 */
#[AsController]
final readonly class PushScriptController
{
    public const string ROUTE = 'beffroi_interaction_push_script';

    public const string FILE = 'push.js';

    public function __construct(
        private ProviderScript $scripts,
    ) {
    }

    #[Route('/push.js', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return $this->scripts->serve($request, self::FILE);
    }
}
