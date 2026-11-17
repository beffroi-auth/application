<?php

declare(strict_types=1);

namespace App\Account;

use function array_map;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\BackchannelAuthenticationRecord;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Oidc\Port\BackchannelAuthenticationRecordRepositoryInterface;
use Beffroi\Oidc\Port\RefreshTokensOfAConsentInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class ApplicationHoldings
{
    public function __construct(
        private RefreshTokensOfAConsentInterface $renewals,
        private BackchannelAuthenticationRecordRepositoryInterface $backchannel,
        private ClockInterface $clock,
    ) {
    }

    public function of(User $user, Consent $consent): ApplicationHolding
    {
        $now = $this->clock->now();

        return $this->holding($consent, $this->backchannel->awaitingAnAnswerBy($user, $now), $now);
    }

    /**
     * @param list<Consent> $consents
     *
     * @return list<ApplicationHolding> in the order the consents were given
     */
    public function ofEach(User $user, array $consents): array
    {
        $now = $this->clock->now();
        $awaiting = $this->backchannel->awaitingAnAnswerBy($user, $now);

        return array_map(
            fn (Consent $consent): ApplicationHolding => $this->holding($consent, $awaiting, $now),
            $consents,
        );
    }

    /**
     * @param list<BackchannelAuthenticationRecord> $awaiting
     */
    private function holding(Consent $consent, array $awaiting, DateTimeImmutable $now): ApplicationHolding
    {
        return ApplicationHolding::of($consent, $this->renewals->ofConsent($consent), $awaiting, $now);
    }
}
