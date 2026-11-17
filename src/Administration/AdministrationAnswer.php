<?php

declare(strict_types=1);

namespace App\Administration;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function json_encode;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the administration API answers (decision 0038): a JSON document, never kept by whatever sits between the API
 * and its client since what it carries is the tenant and its people (RFC 9111 §5.2.2.5, "no-store"), and a refusal of
 * what a request asked once it was let in, as a JSON object with an "error" and its description, the shape of
 * RFC 6749 §5.2. An instant is written in UTC as RFC 3339 §5.6 writes one, and an instant the provider does not hold
 * is null.
 */
final readonly class AdministrationAnswer
{
    public const string CONTENT_TYPE = 'application/json';

    /**
     * @param array<string, mixed> $document
     */
    public static function of(array $document, int $status = Response::HTTP_OK): Response
    {
        return new Response(
            json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            $status,
            [
                'Content-Type' => self::CONTENT_TYPE,
                'Cache-Control' => 'no-store',
            ],
        );
    }

    public static function refusal(int $status, string $error, string $description): Response
    {
        return self::of(['error' => $error, 'error_description' => $description], $status);
    }

    public static function moment(?DateTimeInterface $moment): ?string
    {
        return $moment === null
            ? null
            : DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
