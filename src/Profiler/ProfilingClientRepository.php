<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Application\Token\GrantNames;
use Beffroi\Oidc\Port\ClientRepositoryInterface;
use function implode;

final readonly class ProfilingClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private OpProfile $profile,
    ) {
    }

    public function findByClientId(Tenant $tenant, string $clientId): ?Client
    {
        $client = $this->clients->findByClientId($tenant, $clientId);
        if ($client !== null) {
            $this->profile->record(self::observe($client));
        }

        return $client;
    }

    private static function observe(Client $client): Observation
    {
        return new Observation('Client', 'Client', [
            'client_id' => $client->registration->clientId,
            'name' => $client->registration->presentation->name,
            'authentication method' => $client->registration->authenticationMethod->name(),
            'pushed authorization request' => $client->registration->requiresPushedAuthorizationRequests ? 'required' : 'not required',
            'PKCE' => $client->registration->requiresPkce ? 'required' : 'not required',
            'consent' => $client->registration->requiresConsent ? 'asked' : 'not asked',
            'grant types' => implode(', ', GrantNames::of($client->registration->grantTypes)),
            'allowed scopes' => (string) $client->registration->allowedScopes,
            'access token' => $client->registration->accessTokenShape->name(),
            'ID token' => RegisteredShape::of($client->registration->idToken),
            'UserInfo' => RegisteredShape::of($client->registration->userInfo),
            'authorization response' => RegisteredShape::of($client->registration->authorizationResponse),
        ]);
    }
}
