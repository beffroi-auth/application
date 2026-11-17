<?php

declare(strict_types=1);

namespace App\Administration;

use App\Plane\Plane;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * The requests of the administration API (decision 0038), for the firewall of Symfony that leaves them alone: the API
 * is guarded by the token of each request (`AdministrationGuard`), never by a session, so no cookie is read there and
 * no session is started. Read from the name of the route, as the plane of every route is (`Plane::ofRoute()`).
 */
final readonly class AdministrationRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        return Plane::ofRoute($request->attributes->getString('_route')) === Plane::Administration;
    }
}
