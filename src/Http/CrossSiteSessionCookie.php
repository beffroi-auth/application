<?php

declare(strict_types=1);

namespace App\Http;

use App\Plane\Plane;
use function in_array;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Takes the session cookie off the answer to a cross-site request, other than a GET or a HEAD, that carried none:
 * a "SameSite=Lax" cookie does not accompany it, and the new cookie would replace the browser's (beffroi/saml S-28).
 */
final readonly class CrossSiteSessionCookie
{
    /**
     * Below AbstractSessionListener (-1000), which saves the session and writes its cookie.
     */
    public const int AFTER_THE_SESSION_IS_WRITTEN = -1024;

    private const array LAX = [Request::METHOD_GET, Request::METHOD_HEAD];

    public static function withheldFrom(Request $request): bool
    {
        return (
            ! in_array($request->getMethod(), self::LAX, true)
            && $request->headers->get('Sec-Fetch-Site') === 'cross-site'
            && ! $request->hasPreviousSession()
        );
    }

    #[AsEventListener(event: ResponseEvent::class, priority: self::AFTER_THE_SESSION_IS_WRITTEN)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (
            ! $event->isMainRequest()
            || Plane::ofRoute($request->attributes->getString('_route')) !== Plane::Interaction
            || ! $request->hasSession(true)
            || ! self::withheldFrom($request)
        ) {
            return;
        }
        $name = $request->getSession()->getName();
        $headers = $event->getResponse()->headers;
        foreach ($headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                $headers->removeCookie($name, $cookie->getPath(), $cookie->getDomain());
            }
        }
    }
}
