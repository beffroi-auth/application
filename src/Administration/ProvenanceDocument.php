<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Core\Domain\Session\RequestContext;
use function array_filter;
use function implode;

/**
 * Where a session or an act came from, as the administration API writes it: the address, the user agent and the
 * place the address was found in, city, region and country, the coordinates staying out of what the API answers
 * (architecture §3.5).
 */
final readonly class ProvenanceDocument
{
    /**
     * @return array{address: string|null, user_agent: string|null, place: string|null}
     */
    public static function of(RequestContext $context): array
    {
        $location = $context->location;
        $place = $location === null
            ? []
            : array_filter([$location->city, $location->region, $location->countryCode], static fn (?string $part): bool => $part !== null && $part !== '');

        return [
            'address' => $context->ipAddress,
            'user_agent' => $context->userAgent,
            'place' => $place === [] ? null : implode(', ', $place),
        ];
    }
}
