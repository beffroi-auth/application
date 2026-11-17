<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the administration API of a tenant (decision 0038), declared on its controllers, which the kernel of
 * a process whose plane is "administration" loads in place of every other route (App\Kernel), and which no other
 * process loads. Every route name carries the plane, and the last of them answers every path the others do not, so
 * that a request is held to the guard of the API before it learns whether its path exists.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../src/Administration/Controller/', 'attribute');
};
