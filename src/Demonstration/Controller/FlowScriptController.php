<?php

declare(strict_types=1);

namespace App\Demonstration\Controller;

use function dirname;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class FlowScriptController
{
    public const string ROUTE = 'app_demonstration_script';

    #[Route('/flow.js', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $response = new BinaryFileResponse(
            dirname(__DIR__) . '/assets/flow.js',
            Response::HTTP_OK,
            ['Content-Type' => 'text/javascript; charset=utf-8'],
            false,
        );
        $response->setAutoEtag();
        $response->isNotModified($request);

        return $response;
    }
}
