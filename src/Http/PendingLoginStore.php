<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Session\PendingLogin;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The login held between its two steps, in the HTTP session. An expired one is held as well: the core refuses it at
 * the second step, which tells a browser that was late from one that held nothing.
 */
final readonly class PendingLoginStore
{
    public const string KEY = '_beffroi_pending_login';

    public function hold(SessionInterface $session, PendingLogin $login): void
    {
        $session->set(self::KEY, $login);
    }

    public function held(SessionInterface $session): ?PendingLogin
    {
        $login = $session->get(self::KEY);

        return $login instanceof PendingLogin ? $login : null;
    }

    public function forget(SessionInterface $session): void
    {
        $session->remove(self::KEY);
    }
}
