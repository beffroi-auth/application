<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\AdministrationAnswer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Every path the administration API does not serve, whatever its method: routed, so that the request is held to the
 * guard of the API like any other and learns that its path does not exist only once it was let in (decision 0038).
 * The last of the routes of the API.
 */
#[AsController]
final readonly class UnknownPathController
{
    #[Route('/{path}', name: 'beffroi_administration_unknown_path', requirements: ['path' => '.*'], priority: -1000)]
    public function __invoke(): Response
    {
        return AdministrationAnswer::refusal(Response::HTTP_NOT_FOUND, 'not_found', 'The administration API serves nothing at this path.');
    }
}
