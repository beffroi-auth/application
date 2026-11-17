<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Imported at the root, a tenant's issuer being an origin; BEFFROI_PLANE reads the plane in every route name.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../src/Controller/', 'attribute');
};
