<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Development only: the FIDO2 server API the FIDO Conformance Tools drive, not an API of the product.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../../src/Fido/Conformance/', 'attribute');
};
