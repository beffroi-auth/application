<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Saml\Domain\SingleLogout\LogoutChain;
use Beffroi\Saml\Domain\SingleLogout\LogoutChainSecret;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * beffroi/saml S-32
 */
final readonly class LogoutChainCookie
{
    /**
     * The "__Host-" prefix makes a browser refuse the cookie unless it is Secure, on path "/" and names no Domain:
     * no subdomain of the provider can write it or read it.
     */
    public const string NAME = '__Host-beffroi_saml_logout';

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function presented(Request $request): ?LogoutChainSecret
    {
        $value = $request->cookies->getString(self::NAME);

        return $value === '' ? null : new LogoutChainSecret($value);
    }

    public function given(Response $response, LogoutChainSecret $secret): Response
    {
        /**
         * SameSite=None: a service provider posts the next step from its own site, a cross-site post under which a
         * browser holds back a Lax cookie.
         */
        $response->headers->setCookie(Cookie::create(
            self::NAME,
            $secret->value,
            $this->clock->now()->getTimestamp() + LogoutChain::TTL,
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_NONE,
        ));

        return $response;
    }

    public function cleared(Response $response): Response
    {
        $response->headers->clearCookie(self::NAME, '/', null, true, true, Cookie::SAMESITE_NONE);

        return $response;
    }
}
