<?php

declare(strict_types=1);

namespace App\Routing;

use Symfony\Component\Routing\DependencyInjection\Router;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * What writes the URLs of the routes of the provider, its endpoints (RouterEndpointLocator) and the routes the
 * application names as a client of it (SelfClient), in every process: the router itself in a process of the planes of
 * the provider, which loads those routes; and, in a process of the administration API, which loads the routes of the
 * API alone (decision 0038), a router of the routes of the provider that writes their URLs and is asked to match no
 * request, the router of that process answering the requests. Its kernel says where the routes of the provider are
 * read from, which is what tells one process from the other here.
 */
final readonly class ProviderRoutes
{
    /**
     * The service that writes the URLs of the routes of the provider.
     */
    public const string URL_GENERATOR = 'beffroi.provider_url_generator';

    /**
     * Where the routes of the provider are read from, set by the kernel of a process that does not load them.
     */
    public const string RESOURCE = 'beffroi.provider_routes.resource';

    /**
     * The type of that resource, null for a file whose extension says it.
     */
    public const string TYPE = 'beffroi.provider_routes.type';

    public static function register(ServicesConfigurator $services, ContainerBuilder $builder): void
    {
        if (! $builder->hasParameter(self::RESOURCE)) {
            $services->alias(self::URL_GENERATOR, 'router');

            return;
        }
        $services->set(self::URL_GENERATOR, Router::class)->args([
            service_locator([
                'routing.loader' => service('routing.loader'),
            ]),
            param(self::RESOURCE),
            [
                'cache_dir' => '%kernel.cache_dir%/provider_routes',
                'debug' => '%kernel.debug%',
                'generator_class' => CompiledUrlGenerator::class,
                'generator_dumper_class' => CompiledUrlGeneratorDumper::class,
                'resource_type' => param(self::TYPE),
            ],
            service('router.request_context'),
            service('parameter_bag'),
        ]);
    }
}
