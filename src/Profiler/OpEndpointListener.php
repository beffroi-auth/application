<?php

declare(strict_types=1);

namespace App\Profiler;

use App\Http\DpopProofHeader;
use App\Http\Tls\ClientCertificateResolverInterface;
use App\Http\Tls\ForgedClientCertificateException;
use App\Plane\Plane;
use Beffroi\Oidc\Exception\InvalidDpopProofException;
use Beffroi\Oidc\Exception\InvalidArgumentException;
use function str_starts_with;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class OpEndpointListener
{
    /**
     * Below PlaneRequestListener, so that a route this process refuses is never reported as served.
     */
    public const int PRIORITY = 30;

    public function __construct(
        private OpProfile $profile,
        private ClientCertificateResolverInterface $certificates,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (! $event->isMainRequest() || ! self::isAnEndpointOfTheProvider($route)) {
            return;
        }
        $this->profile->touch();
        $this->profile->record(new Observation('Request', 'Endpoint', [
            'route' => $route,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'client certificate' => $this->certificate($request),
            'DPoP proof' => self::proof($request),
        ]));
    }

    private static function isAnEndpointOfTheProvider(string $route): bool
    {
        return str_starts_with($route, Plane::INTERACTION_ROUTE_PREFIX) || str_starts_with($route, Plane::PROTOCOL_ROUTE_PREFIX);
    }

    /**
     * RFC 8705 §3.1
     */
    private function certificate(Request $request): string
    {
        try {
            $certificate = $this->certificates->ofRequest($request);
        } catch (ForgedClientCertificateException) {
            return 'carried in a header, refused';
        } catch (InvalidArgumentException) {
            return 'handed over by the edge, and unreadable';
        }

        return $certificate === null ? 'none' : 'x5t#S256 ' . $certificate->x5tS256();
    }

    private static function proof(Request $request): string
    {
        try {
            return DpopProofHeader::ofRequest($request) === null ? 'none' : 'presented';
        } catch (InvalidDpopProofException) {
            return 'more than one, refused';
        }
    }
}
