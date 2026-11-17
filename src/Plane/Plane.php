<?php

declare(strict_types=1);

namespace App\Plane;

use function is_string;
use function str_starts_with;

/**
 * The plane a process serves, read from BEFFROI_PLANE; every route of the provider carries its plane in its name.
 */
enum Plane: string
{
    case Provider = 'provider';
    case Interaction = 'interaction';
    case Protocol = 'protocol';
    case Administration = 'administration';

    public const string INTERACTION_ROUTE_PREFIX = 'beffroi_interaction_';

    public const string PROTOCOL_ROUTE_PREFIX = 'beffroi_protocol_';

    public const string ADMINISTRATION_ROUTE_PREFIX = 'beffroi_administration_';

    public const string VARIABLE = 'BEFFROI_PLANE';

    /**
     * The plane the listener of the planes and the guard of the administration API read: the one the kernel of the
     * administration was built for, or BEFFROI_PLANE, read when the process runs, for any other kernel.
     */
    public const string PARAMETER = 'beffroi.plane';

    public static function ofRoute(string $routeName): ?self
    {
        return match (true) {
            str_starts_with($routeName, self::INTERACTION_ROUTE_PREFIX) => self::Interaction,
            str_starts_with($routeName, self::PROTOCOL_ROUTE_PREFIX) => self::Protocol,
            str_starts_with($routeName, self::ADMINISTRATION_ROUTE_PREFIX) => self::Administration,
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $environment
     */
    public static function isTheAdministrationIn(array $environment): bool
    {
        $value = $environment[self::VARIABLE] ?? null;

        return is_string($value) && self::tryFrom($value) === self::Administration;
    }

    public function serves(string $routeName): bool
    {
        $plane = self::ofRoute($routeName);

        return $plane === null || $plane === $this || $this === self::Provider && $plane !== self::Administration;
    }
}
