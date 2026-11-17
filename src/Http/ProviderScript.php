<?php

declare(strict_types=1);

namespace App\Http;

use function dirname;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Served from the provider's own origin, so that a policy allowing only that origin runs it; cached, so it carries
 * nothing of a user or of a tenant.
 */
final readonly class ProviderScript
{
    public const int MAX_AGE = 3600;

    public function serve(Request $request, string $file): Response
    {
        $response = new BinaryFileResponse(
            dirname(__DIR__, 2) . '/scripts/' . $file,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/javascript; charset=utf-8',
            ],
            false,
        );
        $response->setAutoEtag();
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->isNotModified($request);

        return $response;
    }
}
