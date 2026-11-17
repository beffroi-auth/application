<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Development only: the "automated_ciba_approval_url" of the conformance suite, not an API of the product.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../../src/Ciba/Conformance/', 'attribute');
};
