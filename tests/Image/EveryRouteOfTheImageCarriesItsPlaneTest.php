<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Kernel;
use App\Plane\Plane;
use function array_keys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decision 0019
 */
final class EveryRouteOfTheImageCarriesItsPlaneTest extends TestCase
{
    #[Test]
    #[DataProvider('provideTheRoutesOfProduction')]
    public function aRouteOfProductionCarriesItsPlane(string $name): void
    {
        // When
        $plane = Plane::ofRoute($name);

        // Then
        static::assertNotNull($plane);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheRoutesOfProduction(): iterable
    {
        $kernel = new Kernel('prod', false);
        $kernel->boot();
        try {
            $router = $kernel->getContainer()->get('router');
            static::assertInstanceOf(RouterInterface::class, $router);
            foreach (array_keys($router->getRouteCollection()->all()) as $name) {
                yield $name => [$name];
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
