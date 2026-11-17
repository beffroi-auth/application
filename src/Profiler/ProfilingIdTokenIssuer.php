<?php

declare(strict_types=1);

namespace App\Profiler;

use DateTimeImmutable;
use Beffroi\Oidc\Domain\Token\AddressedIdToken;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Token\AccessToken;
use Beffroi\Oidc\Domain\Token\UserAuthorizationInterface;
use Beffroi\Oidc\Exception\InvalidJwtException;
use Beffroi\Oidc\Port\CompactJwtReaderInterface;
use Beffroi\Oidc\Port\IdTokenIssuerInterface;
use function array_keys;
use function implode;
use const DATE_ATOM;

final readonly class ProfilingIdTokenIssuer implements IdTokenIssuerInterface
{
    public function __construct(
        private IdTokenIssuerInterface $issuer,
        private OpProfile $profile,
        private CompactJwtReaderInterface $jwts,
        private PanelValues $values,
    ) {
    }

    public function address(Client $client): AddressedIdToken
    {
        return $this->issuer->address($client);
    }

    public function issue(UserAuthorizationInterface $authorization, AccessToken $accessToken, AddressedIdToken $addressed, DateTimeImmutable $now): string
    {
        $idToken = $this->issuer->issue($authorization, $accessToken, $addressed, $now);
        $requested = $authorization->claimsRequest->idToken ?? [];
        $this->profile->record(new Observation('Tokens', 'ID token', [
            'aud' => $authorization->client->registration->clientId,
            'shape' => RegisteredShape::of($authorization->client->registration->idToken),
            'nonce' => $authorization->nonce === null ? 'none' : 'carried back',
            'acr' => $authorization->authentication->contextClass,
            'amr' => implode(' ', $authorization->authentication->methods),
            'auth_time' => $authorization->authentication->authenticatedAt->format(DATE_ATOM),
            'claims the client asked for by name' => $requested === [] ? 'none' : implode(', ', array_keys($requested)),
            'claims it carries' => $this->claimsOf($idToken),
        ]));

        return $idToken;
    }

    private function claimsOf(string $idToken): string
    {
        try {
            return $this->values->all($this->jwts->read($idToken)->claims);
        } catch (InvalidJwtException) {
            return 'encrypted for the client, and not opened here';
        }
    }
}
