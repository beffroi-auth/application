<?php

declare(strict_types=1);

namespace App\Profiler;

use DateTimeImmutable;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Token\AccessToken;
use Beffroi\Oidc\Domain\Token\AddressedAccessToken;
use Beffroi\Oidc\Domain\Token\AuthorizationInterface;
use Beffroi\Oidc\Domain\Token\UserAuthorizationInterface;
use Beffroi\Oidc\Exception\InvalidJwtException;
use Beffroi\Oidc\Port\AccessTokenIssuerInterface;
use Beffroi\Oidc\Port\CompactJwtReaderInterface;
use function implode;
use function sprintf;
use const DATE_ATOM;

final readonly class ProfilingAccessTokenIssuer implements AccessTokenIssuerInterface
{
    public function __construct(
        private AccessTokenIssuerInterface $issuer,
        private OpProfile $profile,
        private CompactJwtReaderInterface $jwts,
    ) {
    }

    public function address(Client $client): AddressedAccessToken
    {
        return $this->issuer->address($client);
    }

    public function addressFor(AuthorizationInterface $authorization): AddressedAccessToken
    {
        return $this->issuer->addressFor($authorization);
    }

    public function issue(AuthorizationInterface $authorization, AddressedAccessToken $addressed, DateTimeImmutable $now): AccessToken
    {
        $token = $this->issuer->issue($authorization, $addressed, $now);
        $confirmation = $authorization->confirmation;
        $this->profile->record(new Observation('Tokens', 'Access token', [
            'grant' => $authorization->grantType->value,
            'shape' => $authorization->client->registration->accessTokenShape->name(),
            'jti' => $this->jtiOf($token),
            'aud' => implode(' ', $authorization->audience()->values),
            'exp' => $token->expiresAt->format(DATE_ATOM),
            'scopes of this response' => (string) $authorization->scopes,
            'scopes granted' => (string) $authorization->grantedScopes,
            'binding' => $confirmation === null ? 'bearer' : sprintf('cnf.%s %s', $confirmation->member(), $confirmation->value()),
            ...self::whoSignedIn($authorization),
        ]));

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private static function whoSignedIn(AuthorizationInterface $authorization): array
    {
        if (! $authorization instanceof UserAuthorizationInterface) {
            return [
                'signed in' => 'nobody: the client asked for itself',
            ];
        }

        return [
            'acr' => $authorization->authentication->contextClass,
            'amr' => implode(' ', $authorization->authentication->methods),
            'auth_time' => $authorization->authentication->authenticatedAt->format(DATE_ATOM),
        ];
    }

    private function jtiOf(AccessToken $token): string
    {
        try {
            return $this->jwts->read($token->value)->claim('jti') ?? 'none';
        } catch (InvalidJwtException) {
            return 'none: the token carries no claims';
        }
    }
}
