<?php

declare(strict_types=1);

namespace App\Profiler;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Consent\Consent;
use function implode;
use Beffroi\Oidc\Port\ConsentRepositoryInterface;
use const DATE_ATOM;

final readonly class ProfilingConsentRepository implements ConsentRepositoryInterface
{
    public function __construct(
        private ConsentRepositoryInterface $consents,
        private OpProfile $profile,
    ) {
    }

    public function findActive(User $user, Client $client): ?Consent
    {
        $consent = $this->consents->findActive($user, $client);
        if ($consent !== null) {
            $this->profile->record(self::observe($consent, 'found'));
        }

        return $consent;
    }

    public function save(Consent $consent): void
    {
        $this->consents->save($consent);
        $this->profile->record(self::observe($consent, 'granted'));
    }

    private static function observe(Consent $consent, string $state): Observation
    {
        return new Observation('Client', 'Consent', [
            'client_id' => $consent->clientId,
            'state' => $state,
            'scopes consented' => (string) $consent->scopes,
            'claims consented by name' => implode(' ', $consent->claims->all),
            'granted at' => $consent->grantedAt->format(DATE_ATOM),
        ]);
    }
}
