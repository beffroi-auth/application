<?php

declare(strict_types=1);

namespace App\Push;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the sender of web push a client built as the transport of the application is, its default options included,
 * without the logger of that transport: the line it logs for each request names the endpoint, which names the browser
 * of a person.
 */
final class WebPushHttpClientPass implements CompilerPassInterface
{
    public const string CLIENT = 'beffroi.web_push.http_client';

    private const string APPLICATION_TRANSPORT = 'http_client.transport';

    private const string LOGGER_TAG = 'monolog.logger';

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(self::APPLICATION_TRANSPORT) || ! $container->hasDefinition(WebPushSender::class)) {
            return;
        }
        $transport = $container->getDefinition(self::APPLICATION_TRANSPORT);
        $client = new Definition($transport->getClass(), $transport->getArguments())->setFactory($transport->getFactory());
        /** @var array<string, list<array<string, mixed>>> $tagged */
        $tagged = $transport->getTags();
        foreach ($tagged as $name => $tags) {
            if ($name === self::LOGGER_TAG) {
                continue;
            }
            foreach ($tags as $attributes) {
                $client->addTag($name, $attributes);
            }
        }
        $container->setDefinition(self::CLIENT, $client);
        $container->getDefinition(WebPushSender::class)->setArgument('$http', new Reference(self::CLIENT));
    }
}
