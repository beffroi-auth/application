<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Oidc\Domain\Client\Registration\ClientInformationResponse;
use Beffroi\Oidc\Domain\Client\Registration\RegistrationErrorResponse;
use Beffroi\Oidc\Exception\UnauthorizedBearerRequestException;
use Beffroi\Oidc\Exception\UnreadableClientMetadataException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * RFC 7591 §3.2, RFC 7592 §2, §3, RFC 6750 §3
 */
final readonly class ClientRegistrationAnswer
{
    public static function information(ClientInformationResponse $response): Response
    {
        return ProtocolAnswer::json(
            $response->parameters(),
            $response->statusCode,
            ClientInformationResponse::CONTENT_TYPE,
            ClientInformationResponse::HEADERS,
        );
    }

    /**
     * RFC 7592 §2.3
     */
    public static function gone(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT, ClientInformationResponse::HEADERS);
    }

    public static function refusal(UnreadableClientMetadataException $exception): Response
    {
        return ProtocolAnswer::json(
            RegistrationErrorResponse::fromException($exception)->parameters(),
            RegistrationErrorResponse::STATUS_CODE,
            RegistrationErrorResponse::CONTENT_TYPE,
            ClientInformationResponse::HEADERS,
        );
    }

    public static function challenge(UnauthorizedBearerRequestException $exception): Response
    {
        return ProtocolAnswer::error($exception, [
            'WWW-Authenticate' => $exception->challenge(),
        ] + ClientInformationResponse::HEADERS);
    }

    /**
     * RFC 7591 §3.1
     *
     * @return array<string, mixed>|null
     */
    public static function members(Request $request): ?array
    {
        try {
            $members = $request->toArray();
        } catch (Throwable) {
            return null;
        }
        $named = [];
        foreach ($members as $member => $value) {
            $named[(string) $member] = $value;
        }

        return $named;
    }
}
