<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The token of the forms of the logins, checked before the core is handed anything and before a ceremony is taken out
 * of the session: Symfony's CSRF badge is checked once the passport is built, after both.
 */
final readonly class LoginFormToken
{
    /**
     * The provider's own, so that no stateless token identifier an application declares covers these forms.
     */
    public const string ID = 'beffroi_login';

    public const string PARAMETER = '_csrf_token';

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokens,
    ) {
    }

    /**
     * @throws InvalidCsrfTokenException
     */
    public function check(Request $request): void
    {
        if (! $this->csrfTokens->isTokenValid(new CsrfToken(self::ID, $request->request->getString(self::PARAMETER)))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }
    }
}
