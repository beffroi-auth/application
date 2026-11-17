<?php

declare(strict_types=1);

namespace App\Key;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Hands every KMS factory that talks HTTP the client of the application with a wait bounded under the execution time
 * of PHP: a KMS that stops answering is an outage within seconds, never a request or the start of a thread killed by
 * the time limit. A DSN that names its own "timeout" or "max_duration" keeps it.
 */
final class KmsHttpClientPass implements CompilerPassInterface
{
    public const string CLIENT = 'beffroi.kms.http_client';

    /**
     * In seconds, the idle timeout and the longest duration of a request, the ones the bridges give a client they
     * build themselves.
     */
    public const float TIMEOUT = 5.0;

    public const float MAX_DURATION = 10.0;

    private const string APPLICATION_CLIENT = 'http_client';

    public function process(ContainerBuilder $container): void
    {
        if (! $container->has(self::APPLICATION_CLIENT)) {
            return;
        }

        $container
            ->register(self::CLIENT, HttpClientInterface::class)
            ->setFactory([new Reference(self::APPLICATION_CLIENT), 'withOptions'])
            ->setArguments([['timeout' => self::TIMEOUT, 'max_duration' => self::MAX_DURATION]]);

        foreach ($container->findTaggedServiceIds('key_management.factory') as $id => $tags) {
            $definition = $container->getDefinition($id);
            foreach ($definition->getArguments() as $index => $argument) {
                if ($argument instanceof Reference && (string) $argument === self::APPLICATION_CLIENT) {
                    $definition->replaceArgument($index, new Reference(self::CLIENT, $argument->getInvalidBehavior()));
                }
            }
        }
    }
}
