<?php

declare(strict_types=1);

namespace App\Saml;

use function array_filter;
use function array_slice;
use Beffroi\Core\Domain\Encoding\Base64Url;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use DateTimeImmutable;
use function hash_equals;
use function is_array;
use function is_int;
use function is_string;
use function random_bytes;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * One-time references kept in the HTTP session, without which another site sending the browser to the sign-on
 * starts nothing (beffroi/saml S-51).
 *
 * @phpstan-type Kept array{reference: string, identity_provider: string, issued_at: int}
 */
final readonly class UpstreamSignOnReferences
{
    public const string SESSION_KEY = '_beffroi_saml_upstream_sign_on_references';

    public const int MAX_KEPT = 8;

    /**
     * In seconds (spec/saml.md section 7).
     */
    public const int LIFE = 60;

    private const int BYTES = 32;

    /**
     * The key of the login a page waits for: no interaction identifier, being base64url, holds a colon.
     */
    private const string WAITING_PAGE = ':page';

    /**
     * @param Interaction|null $interaction null for the login a page of the provider waits for
     */
    public function issue(SessionInterface $httpSession, ?Interaction $interaction, string $identityProvider, DateTimeImmutable $now): string
    {
        $reference = Base64Url::encode(random_bytes(self::BYTES));
        $kept = self::living($httpSession, $now);
        $key = self::key($interaction);
        unset($kept[$key]);
        $kept[$key] = [
            'reference' => $reference,
            'identity_provider' => $identityProvider,
            'issued_at' => $now->getTimestamp(),
        ];
        $httpSession->set(self::SESSION_KEY, array_slice($kept, -self::MAX_KEPT, preserve_keys: true));

        return $reference;
    }

    /**
     * Forgets the reference whatever was presented, so that it starts one sign-on at most.
     *
     * @param Interaction|null $interaction null for the login a page of the provider waits for
     */
    public function take(
        SessionInterface $httpSession,
        ?Interaction $interaction,
        string $identityProvider,
        string $presented,
        DateTimeImmutable $now,
    ): bool {
        $kept = self::living($httpSession, $now);
        $key = self::key($interaction);
        $entry = $kept[$key] ?? null;
        unset($kept[$key]);
        $httpSession->set(self::SESSION_KEY, $kept);

        return $entry !== null && hash_equals($entry['reference'], $presented) && $entry['identity_provider'] === $identityProvider;
    }

    private static function key(?Interaction $interaction): string
    {
        return $interaction->id ?? self::WAITING_PAGE;
    }

    /**
     * @return array<string, Kept>
     */
    private static function living(SessionInterface $httpSession, DateTimeImmutable $now): array
    {
        return array_filter(
            self::kept($httpSession),
            static fn (array $kept): bool => ($kept['issued_at'] + self::LIFE) >= $now->getTimestamp(),
        );
    }

    /**
     * @return array<string, Kept>
     */
    private static function kept(SessionInterface $httpSession): array
    {
        $stored = $httpSession->get(self::SESSION_KEY);
        if (! is_array($stored)) {
            return [];
        }
        $kept = [];
        foreach ($stored as $login => $entry) {
            $reference = is_array($entry) ? $entry['reference'] ?? null : null;
            $identityProvider = is_array($entry) ? $entry['identity_provider'] ?? null : null;
            $issuedAt = is_array($entry) ? $entry['issued_at'] ?? null : null;
            if (is_string($login) && is_string($reference) && is_string($identityProvider) && is_int($issuedAt)) {
                $kept[$login] = [
                    'reference' => $reference,
                    'identity_provider' => $identityProvider,
                    'issued_at' => $issuedAt,
                ];
            }
        }

        return $kept;
    }
}
