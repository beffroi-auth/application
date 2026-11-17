<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\BeffroiKernelTrait;
use App\Plane\Plane;
use App\Routing\ProviderRoutes;
use function dirname;
use LogicException;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * The kernel of the application. A process whose plane is the administration API (decision 0038) loads the routes of
 * that API and no other, and a process of any other plane never loads them: which one a kernel is, it reads from its
 * environment when it is built (Plane::VARIABLE), and the two keep their compiled routes, and with them their
 * container, in cache directories of their own.
 *
 * When it is built, and never after: a FrankenPHP worker builds it under the environment its site gives it, and puts
 * the environment of the container back in every request it serves, where a deployment may name another plane.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        configureRoutes as private configureTheRoutesOfTheProvider;
        getCacheDir as private cacheDirectoryOfTheProvider;
        getKernelParameters as private kernelParametersOfTheProvider;
    }
    use BeffroiKernelTrait;

    private readonly bool $administration;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        $this->administration = Plane::isTheAdministrationIn($_SERVER);
    }

    public function getCacheDir(): string
    {
        $directory = $this->cacheDirectoryOfTheProvider();

        return $this->administration ? $directory . '-' . Plane::Administration->value : $directory;
    }

    /**
     * The routes of the provider, which the process of the administration API reads to write the URLs of the provider
     * and never to serve them (ProviderRoutes), as a process of the provider configures them.
     */
    public function loadTheRoutesOfTheProvider(LoaderInterface $loader): RouteCollection
    {
        $file = (string) new ReflectionObject($this)->getFileName();
        $kernelLoader = $loader->getResolver()->resolve($file, 'php');
        if (! $kernelLoader instanceof PhpFileLoader) {
            throw new LogicException('The routes of the provider are read by the loader of the PHP files of the kernel.');
        }
        $kernelLoader->setCurrentDir(dirname($file));
        $routes = new RouteCollection();
        $this->configureTheRoutesOfTheProvider(new RoutingConfigurator($routes, $kernelLoader, $file, $file, $this->getEnvironment()));

        return $routes;
    }

    /**
     * Where the process of the administration API reads the routes of the provider from (ProviderRoutes), and its
     * plane.
     *
     * @return array<string, mixed>
     */
    protected function getKernelParameters(): array
    {
        $parameters = $this->kernelParametersOfTheProvider();
        if ($this->administration) {
            $parameters[ProviderRoutes::RESOURCE] = 'kernel::loadTheRoutesOfTheProvider';
            $parameters[ProviderRoutes::TYPE] = 'service';
            $parameters[Plane::PARAMETER] = Plane::Administration;
        }

        return $parameters;
    }

    /**
     * @return list<string>
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        if ($this->administration) {
            $routes->import($this->getConfigDir() . '/administration/routes.php');

            return;
        }
        $this->configureTheRoutesOfTheProvider($routes);
    }
}
