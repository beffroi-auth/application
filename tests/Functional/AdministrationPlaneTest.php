<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Kernel;
use App\Plane\Plane;
use App\Plane\PlaneRequestListener;
use App\Routing\ProviderRoutes;
use function array_keys;
use function array_values;
use function array_filter;
use PHPUnit\Framework\Attributes\Test;
use function str_starts_with;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decision 0038: the kernel of this application loads the routes of the administration API in a process whose plane
 * is that API, and in no other, and the routes of the provider in every other process: a deployment that runs no
 * process of the administration plane has no such API, rather than having it and refusing it. The plane is read when
 * the kernel is built, and each kernel keeps its compiled routes in a cache of its own.
 */
final class AdministrationPlaneTest extends KernelTestCase
{
    private ?Kernel $built = null;

    protected function tearDown(): void
    {
        $this->built?->shutdown();
        $this->built = null;
        parent::tearDown();
        unset($_SERVER[Plane::VARIABLE], $_ENV[Plane::VARIABLE]);
    }

    #[Test]
    public function theKernelOfTheProviderLoadsNoRouteOfTheApi(): void
    {
        // When
        $names = self::routeNames(self::bootKernel());

        // Then
        static::assertSame([], self::administrationRoutesAmong($names));
    }

    #[Test]
    public function theKernelOfTheAdministrationLoadsTheRoutesOfTheApiAlone(): void
    {
        // Given
        $_SERVER[Plane::VARIABLE] = Plane::Administration->value;

        // When
        $names = self::routeNames(self::bootKernel());

        // Then
        static::assertSame($names, self::administrationRoutesAmong($names));
    }

    /**
     * The process of the API writes the URLs of the provider, a token endpoint its clients authenticate to among
     * them, from the routes of the provider, which it does not serve.
     */
    #[Test]
    public function theKernelOfTheAdministrationWritesTheUrlsOfTheProvider(): void
    {
        // Given
        $_SERVER[Plane::VARIABLE] = Plane::Administration->value;

        // When
        $generator = self::bootKernel()->getContainer()->get('test.service_container')->get(ProviderRoutes::URL_GENERATOR);

        // Then
        static::assertInstanceOf(UrlGeneratorInterface::class, $generator);
        static::assertSame('/token', $generator->generate('beffroi_protocol_token'));
    }

    /**
     * A worker of FrankenPHP is built under the environment of its site, and every request it serves brings the
     * environment of the container back, which may name another plane.
     */
    #[Test]
    public function theKernelOfTheAdministrationKeepsTheRoutesOfThePlaneItWasBuiltIn(): void
    {
        // Given
        $kernel = $this->builtForTheAdministrationThenAskedForTheProvider();

        // When
        $kernel->boot();

        // Then
        $names = self::routeNames($kernel);
        static::assertSame($names, self::administrationRoutesAmong($names));
    }

    #[Test]
    public function theProcessOfTheAdministrationLetsTheApiThroughWhateverPlaneARequestNames(): void
    {
        // Given
        $kernel = $this->builtForTheAdministrationThenAskedForTheProvider();
        $kernel->boot();
        $listener = $kernel->getContainer()->get('test.service_container')->get(PlaneRequestListener::class);
        static::assertInstanceOf(PlaneRequestListener::class, $listener);
        $request = Request::create('/health');
        $request->attributes->set('_route', Plane::ADMINISTRATION_ROUTE_PREFIX . 'health');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // When
        $listener($event);

        // Then
        static::assertNull($event->getResponse());
    }

    #[Test]
    public function eachPlaneKeepsItsRoutesInACacheOfItsOwn(): void
    {
        // Given
        $provider = new Kernel('test', false)->getCacheDir();
        $_SERVER[Plane::VARIABLE] = Plane::Administration->value;

        // When
        $administration = new Kernel('test', false)->getCacheDir();

        // Then
        static::assertNotSame($provider, $administration);
    }

    /**
     * The kernel of a worker of the administration, built at the start of the worker, then serving a request that
     * carries the plane a container sets for the whole of it.
     */
    private function builtForTheAdministrationThenAskedForTheProvider(): Kernel
    {
        $_SERVER[Plane::VARIABLE] = Plane::Administration->value;
        $this->built = new Kernel('test', false);
        $_SERVER[Plane::VARIABLE] = Plane::Provider->value;
        $_ENV[Plane::VARIABLE] = Plane::Provider->value;

        return $this->built;
    }

    /**
     * @return list<string>
     */
    private static function routeNames(KernelInterface $kernel): array
    {
        $router = $kernel->getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);

        return array_keys($router->getRouteCollection()->all());
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function administrationRoutesAmong(array $names): array
    {
        return array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, Plane::ADMINISTRATION_ROUTE_PREFIX)));
    }
}
