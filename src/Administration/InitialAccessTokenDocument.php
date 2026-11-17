<?php

declare(strict_types=1);

namespace App\Administration;

use Beffroi\Oidc\Domain\Client\Registration\InitialAccessToken;
use DateTimeImmutable;

/**
 * RFC 7591 §1.2
 *
 * An initial access token as the administration API writes it, without its value: only the answer to its issuance
 * carries that, once.
 */
final readonly class InitialAccessTokenDocument
{
    /**
     * @return array{id: string, name: string, issued_at: string|null, expires_at: string|null, registrations_allowed: int, registrations_served: int, administration: bool, granted: object|null, revoked_at: string|null, authorises_a_registration: bool}
     */
    public static function of(InitialAccessToken $token, DateTimeImmutable $now): array
    {
        return [
            'id' => $token->id->toRfc4122(),
            'name' => $token->name,
            'issued_at' => AdministrationAnswer::moment($token->issuedAt),
            'expires_at' => AdministrationAnswer::moment($token->expiresAt),
            'registrations_allowed' => $token->registrationsAllowed,
            'registrations_served' => $token->registrationsServed,
            'administration' => $token->administration,
            'granted' => $token->granted === null ? null : (object) $token->granted->members(),
            'revoked_at' => AdministrationAnswer::moment($token->revokedAt),
            'authorises_a_registration' => $token->authorisesARegistration($now),
        ];
    }
}
