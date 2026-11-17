<?php

declare(strict_types=1);

namespace App\Demonstration\Trace\Endpoint;

use function parse_url;
use const PHP_URL_PATH;
use Symfony\Component\HttpFoundation\Request;

final readonly class ServedAt
{
    public static function is(Request $request, string $endpoint): bool
    {
        return parse_url($endpoint, PHP_URL_PATH) === $request->getPathInfo();
    }
}
