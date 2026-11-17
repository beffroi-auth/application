<?php

declare(strict_types=1);

namespace App\Saml;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class LiteralDeclarations
{
    /**
     * @param array<array-key, mixed> $declarations by entity ID
     *
     * @return array<array-key, mixed> those that hold no environment variable, by entity ID
     */
    public static function of(ContainerBuilder $container, array $declarations): array
    {
        $literal = [];
        foreach ($declarations as $entityId => $declaration) {
            $environment = [];
            $container->resolveEnvPlaceholders([$entityId => $declaration], null, $environment);
            if ($environment === []) {
                $literal[$entityId] = $declaration;
            }
        }

        return $literal;
    }
}
