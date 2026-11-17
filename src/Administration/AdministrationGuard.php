<?php

declare(strict_types=1);

namespace App\Administration;

use App\Doctrine\TenantProvider;
use App\Http\DpopProofHeader;
use App\Http\ProtectedResourceRefusals;
use App\Http\RequestContextFactory;
use App\Http\Tls\PresentedClientCertificate;
use App\Load\OriginLoadListener;
use App\Plane\Plane;
use Beffroi\Oidc\Application\Administration\AdministratorOfARequest;
use Beffroi\Oidc\Exception\MissingBearerTokenException;
use Beffroi\Oidc\Exception\OAuth2Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The guard of the administration API of a tenant (decision 0038): every request a process of that plane receives is
 * asked who it comes from (`AdministratorOfARequest`) before its controller runs, and one that is not an
 * administrator's is answered with the refusal of its token, under the challenge of RFC 6750 §3 and RFC 9449 §7.1.
 * Nothing is read for it, and what it is told does not depend on its path: the last route of the API answers every
 * path the others do not, so a request that names none is held here as well (`UnknownPathController`).
 *
 * The administrator a request was found to be is handed to its controller as a request attribute, which
 * `ActingAdministratorResolver` reads. A process of another plane loads no route of the API, and this does nothing
 * there.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class AdministrationGuard
{
    /**
     * Below the load budget of the origin (30), which bounds what a caller who proved nothing yet may ask, and above
     * the firewall (8).
     */
    public const int PRIORITY = OriginLoadListener::PRIORITY - 1;

    public const string ATTRIBUTE = '_beffroi_administrator';

    public function __construct(
        private Plane $plane,
        private TenantProvider $tenants,
        private AdministratorOfARequest $administrators,
        private RequestContextFactory $contexts,
        private PresentedClientCertificate $certificates,
        private ProtectedResourceRefusals $refusals,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // a sub-request is the request being served, which was let in or refused already
        if ($this->plane !== Plane::Administration || ! $event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        try {
            $administrator = $this->administrators->administratorOf(
                $this->tenants->provide(),
                $request->getMethod(),
                $request->getPathInfo(),
                $request->headers->get('Authorization'),
                $this->contexts->fromRequest($request),
                $this->certificates->ofRequest($request),
                DpopProofHeader::ofRequest($request),
            );
        } catch (OAuth2Exception|MissingBearerTokenException $exception) {
            $event->setResponse($this->refusals->of($exception));

            return;
        }
        $request->attributes->set(self::ATTRIBUTE, $administrator);
    }
}
