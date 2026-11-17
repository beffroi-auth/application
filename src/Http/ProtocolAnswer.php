<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Oidc\Domain\Token\JwtPayload;
use Beffroi\Oidc\Domain\Token\TokenErrorResponse;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProtocolAnswer
{
    /**
     * @param array<string, string> $headers
     */
    public static function document(string $body, int $statusCode, string $contentType, array $headers = []): Response
    {
        return new Response($body, $statusCode, [
            'Content-Type' => $contentType,
        ] + $headers);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string> $headers
     */
    public static function json(array $parameters, int $statusCode, string $contentType, array $headers = []): Response
    {
        return self::document(JwtPayload::encode($parameters), $statusCode, $contentType, $headers);
    }

    /**
     * RFC 6749 §5.2
     *
     * @param array<string, string> $headers
     */
    public static function error(OAuth2Exception $exception, array $headers = []): Response
    {
        $error = TokenErrorResponse::fromException($exception);

        return self::json($error->parameters(), $error->statusCode, TokenErrorResponse::CONTENT_TYPE, $headers + $error->headers());
    }
}
