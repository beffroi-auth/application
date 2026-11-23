<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wraps the client of every service tagged as requesting the URLs a client, a registrant or a person chooses in the
 * guard of the deployment, whatever client it was handed before.
 */
final class ThirdPartyRequestsPass implements CompilerPassInterface
{
    public const string TAG = 'beffroi.third_party_requests';

    private const string ARGUMENT = '$http';

    private const string APPLICATION_CLIENT = 'http_client';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $handed = $definition->getArguments()[self::ARGUMENT] ?? new Reference(self::APPLICATION_CLIENT);
            $definition->setArgument(self::ARGUMENT, new Definition(HttpClientInterface::class, [$handed])->setFactory([
                new Reference(OutboundGuardInterface::class),
                'guard',
            ]));
        }
    }
}
