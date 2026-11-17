<?php

declare(strict_types=1);

namespace App\Key;

use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Refuses a "beffroi.keys.kms" that names no client of KeyManagementBundle, saying the name and the ones that would
 * have worked, where the container would only name a missing service id.
 */
final class KmsClientPass implements CompilerPassInterface
{
    /**
     * The alias the provider registers, and the prefix of the clients KeyManagementBundle registers.
     */
    public const string ALIAS = 'beffroi.kms';

    public const string PREFIX = 'key_management.';

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasAlias(self::ALIAS)) {
            return;
        }

        $target = (string) $container->getAlias(self::ALIAS);
        if ($container->has($target)) {
            return;
        }

        throw new LogicException(sprintf(
            'The KMS client "%s" named by the "beffroi.keys.kms" configuration is not registered in "key_management.clients": %s.',
            str_starts_with($target, self::PREFIX) ? substr($target, strlen(self::PREFIX)) : $target,
            self::registered($container),
        ));
    }

    private static function registered(ContainerBuilder $container): string
    {
        $names = [];
        foreach ($container->findTaggedServiceIds('key_management.client') as $id => $tags) {
            $tag = $tags[0] ?? [];
            $name = is_array($tag) ? $tag['key'] ?? $id : $id;
            $names[] = is_string($name) ? $name : $id;
        }

        if ($names === []) {
            return 'no client is registered at all';
        }

        return sprintf('the registered ones are "%s"', implode('", "', $names));
    }
}
