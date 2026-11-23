<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Event\CredentialChangeNotTold;
use Beffroi\Core\Domain\Event\UserAuthenticated;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Domain\Session\AuthenticationSession;
use Beffroi\Core\Port\ActsOfAPersonInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * NIST SP 800-63B-4 §4.6
 *
 * A change no channel delivered is told once, at the first sign-in that follows it, in the browser that signs in.
 */
final readonly class UntoldChange
{
    public const string SESSION_KEY = '_beffroi_untold_change';

    /**
     * Enough to pass the sign-ins of other browsers since this session opened.
     */
    private const int SIGN_INS_READ = 10;

    public function __construct(
        private ActsOfAPersonInterface $acts,
    ) {
    }

    /**
     * True once for the latest change no channel delivered, when it happened after the sign-in that preceded this
     * session.
     */
    public function take(SessionInterface $httpSession, AuthenticationSession $session): bool
    {
        $user = $session->user;
        $untold = $this->acts->lastOf($user, [CredentialChangeNotTold::NAME], 1)[0] ?? null;
        if ($untold === null) {
            return false;
        }
        $id = $untold->id->toRfc4122();
        if ($httpSession->get(self::SESSION_KEY) === $id) {
            return false;
        }
        $before = $this->signInBefore($user, $session->establishedAt);
        if ($before !== null && $untold->occurredAt->getTimestamp() <= $before->getTimestamp()) {
            return false;
        }
        $httpSession->set(self::SESSION_KEY, $id);

        return true;
    }

    /**
     * The journal keeps the moment to the second: the sign-in of this session is the one of its own second.
     */
    private function signInBefore(User $user, DateTimeImmutable $establishedAt): ?DateTimeImmutable
    {
        foreach ($this->acts->lastOf($user, [UserAuthenticated::NAME], self::SIGN_INS_READ) as $signIn) {
            if ($signIn->occurredAt->getTimestamp() < $establishedAt->getTimestamp()) {
                return $signIn->occurredAt;
            }
        }

        return null;
    }
}
