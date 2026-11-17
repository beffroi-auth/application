<?php

declare(strict_types=1);

namespace App\Fido\Conformance;

use function is_array;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServerResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public static function ok(array $body = []): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'errorMessage' => '',
            ...$body,
        ]);
    }

    public static function options(string $optionsJson): JsonResponse
    {
        $options = json_decode($optionsJson, true, 16, JSON_THROW_ON_ERROR);
        $members = [];
        foreach (is_array($options) ? $options : [] as $name => $value) {
            $members[(string) $name] = $value;
        }

        return self::ok($members);
    }

    public static function failed(string $errorMessage): JsonResponse
    {
        return new JsonResponse([
            'status' => 'failed',
            'errorMessage' => $errorMessage,
        ], Response::HTTP_BAD_REQUEST);
    }
}
