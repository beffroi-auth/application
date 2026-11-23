<?php

declare(strict_types=1);

namespace App\Load;

use App\Plane\Plane;
use App\Plane\PlaneRequestListener;
use Beffroi\Core\Exception\LoadBudgetSpentException;
use function str_contains;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Consumes the budget of the origin before any work a route asks for, on the address behind the trusted proxies and
 * never on a "client_id", which a caller can choose for somebody else. An IPv6 address counts by its /64, the prefix
 * before the 64 bits of its interface identifier (RFC 4291 §2.5.1), which a host draws itself (RFC 8981 §3.3.1).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class OriginLoadListener
{
    /**
     * Below the plane (31), which refuses what this process does not serve, and above the firewall (8).
     */
    public const int PRIORITY = PlaneRequestListener::PRIORITY - 1;

    /**
     * Requests with no address are counted together rather than served free, as Symfony's own listener does.
     */
    public const string NO_ADDRESS = 'unknown';

    private const int IPV6_HOST_BYTES = 8;

    public function __construct(
        private LoadBudget $origin,
    ) {
    }

    /**
     * @throws LoadBudgetSpentException
     */
    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (Plane::ofRoute($request->attributes->getString('_route')) === null) {
            return;
        }

        $this->origin->consume(self::origin($request->getClientIp()));
    }

    private static function origin(?string $address): string
    {
        if ($address === null) {
            return self::NO_ADDRESS;
        }

        return str_contains($address, ':') ? IpUtils::anonymize($address, 0, self::IPV6_HOST_BYTES) : $address;
    }
}
