<?php

declare(strict_types=1);

namespace App\Http;

use function array_key_exists;
use function array_filter;
use function array_slice;
use Beffroi\Oidc\Domain\Interaction\Interaction;
use DateTimeImmutable;
use function is_array;
use function is_int;
use function is_string;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The logins of a browser past the step of the address, and the federated address kept for each sign-on, held in
 * the HTTP session and never in a URL, so that no other site hands a sign-on an address (beffroi/saml S-61, S-68).
 */
final readonly class LoginAddresses
{
    public const string SESSION_KEY = '_beffroi_login_addresses';

    public const string SENT_SESSION_KEY = '_beffroi_login_addresses_sent';

    /**
     * Seconds: the wait of a request for a login (beffroi/saml S-18, S-68).
     */
    public const int SENT_WAIT = 600;

    public const int MAX_KEPT = 8;

    /**
     * The key of the login a page waits for: no interaction identifier, base64url, holds a colon.
     */
    private const string WAITING_PAGE = ':page';

    /**
     * @param Interaction|null $interaction null for the login a page of the provider waits for
     */
    public function pass(SessionInterface $httpSession, ?Interaction $interaction, string $identifier): void
    {
        $passed = self::passedOnes($httpSession);
        $key = self::key($interaction);
        unset($passed[$key]);
        $passed[$key] = true;
        $httpSession->set(self::SESSION_KEY, array_slice($passed, -self::MAX_KEPT, preserve_keys: true));
        $httpSession->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);
    }

    public function passed(SessionInterface $httpSession, ?Interaction $interaction): bool
    {
        return array_key_exists(self::key($interaction), self::passedOnes($httpSession));
    }

    public function forget(SessionInterface $httpSession, ?Interaction $interaction): void
    {
        $key = self::key($interaction);
        $passed = self::passedOnes($httpSession);
        if (array_key_exists($key, $passed)) {
            unset($passed[$key]);
            $httpSession->set(self::SESSION_KEY, $passed);
        }
        $sent = self::sentOnes($httpSession);
        if (array_key_exists($key, $sent)) {
            unset($sent[$key]);
            $httpSession->set(self::SENT_SESSION_KEY, $sent);
        }
    }

    /**
     * @param Interaction|null $interaction null for the login a page of the provider waits for
     */
    public function sendOn(SessionInterface $httpSession, ?Interaction $interaction, string $address, DateTimeImmutable $now): void
    {
        $sent = self::waitingOnes($httpSession, $now);
        $key = self::key($interaction);
        unset($sent[$key]);
        $sent[$key] = [
            'address' => $address,
            'sent_at' => $now->getTimestamp(),
        ];
        $httpSession->set(self::SENT_SESSION_KEY, array_slice($sent, -self::MAX_KEPT, preserve_keys: true));
    }

    public function takeSent(SessionInterface $httpSession, ?Interaction $interaction, DateTimeImmutable $now): ?string
    {
        $sent = self::waitingOnes($httpSession, $now);
        $key = self::key($interaction);
        $address = isset($sent[$key]) ? $sent[$key]['address'] : null;
        unset($sent[$key]);
        $httpSession->set(self::SENT_SESSION_KEY, $sent);

        return $address;
    }

    private static function key(?Interaction $interaction): string
    {
        return $interaction->id ?? self::WAITING_PAGE;
    }

    /**
     * @return array<string, true>
     */
    private static function passedOnes(SessionInterface $httpSession): array
    {
        $stored = $httpSession->get(self::SESSION_KEY);
        if (! is_array($stored)) {
            return [];
        }
        $passed = [];
        foreach ($stored as $interaction => $true) {
            if (is_string($interaction) && $true === true) {
                $passed[$interaction] = true;
            }
        }

        return $passed;
    }

    /**
     * @return array<string, array{address: string, sent_at: int}>
     */
    private static function waitingOnes(SessionInterface $httpSession, DateTimeImmutable $now): array
    {
        return array_filter(
            self::sentOnes($httpSession),
            static fn (array $sent): bool => ($sent['sent_at'] + self::SENT_WAIT) >= $now->getTimestamp(),
        );
    }

    /**
     * @return array<string, array{address: string, sent_at: int}>
     */
    private static function sentOnes(SessionInterface $httpSession): array
    {
        $stored = $httpSession->get(self::SENT_SESSION_KEY);
        if (! is_array($stored)) {
            return [];
        }
        $sent = [];
        foreach ($stored as $login => $entry) {
            $address = is_array($entry) ? $entry['address'] ?? null : null;
            $sentAt = is_array($entry) ? $entry['sent_at'] ?? null : null;
            if (is_string($login) && is_string($address) && is_int($sentAt)) {
                $sent[$login] = [
                    'address' => $address,
                    'sent_at' => $sentAt,
                ];
            }
        }

        return $sent;
    }
}
