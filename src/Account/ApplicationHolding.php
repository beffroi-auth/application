<?php

declare(strict_types=1);

namespace App\Account;

use Beffroi\Oidc\Domain\Ciba\BackchannelAuthenticationRecord;
use Beffroi\Oidc\Domain\Consent\Consent;
use Beffroi\Oidc\Domain\Token\RefreshToken;
use DateTimeImmutable;
use function array_filter;
use function array_values;

final readonly class ApplicationHolding
{
    /**
     * @param list<BackchannelAuthenticationRecord> $pending oldest first
     */
    private function __construct(
        public Consent $consent,
        public int $renewable,
        public ?DateTimeImmutable $lastIssued,
        public array $pending,
    ) {
    }

    /**
     * @param list<RefreshToken> $refreshTokens whatever their state
     * @param list<BackchannelAuthenticationRecord> $awaiting for every client of this person
     */
    public static function of(Consent $consent, array $refreshTokens, array $awaiting, DateTimeImmutable $now): self
    {
        $renewable = 0;
        $lastIssued = null;
        foreach ($refreshTokens as $token) {
            // Its own expiry covers its family's: the model refuses a refresh token that outlives its family.
            if ($token->isRevoked() || $token->isRotated() || $token->expiresAt <= $now) {
                continue;
            }
            ++$renewable;
            if ($lastIssued === null || $token->issuedAt > $lastIssued) {
                $lastIssued = $token->issuedAt;
            }
        }

        return new self(
            $consent,
            $renewable,
            $lastIssued,
            array_values(array_filter(
                $awaiting,
                static fn (BackchannelAuthenticationRecord $record): bool => $record->clientId === $consent->clientId,
            )),
        );
    }
}
