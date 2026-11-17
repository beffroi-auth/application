<?php

declare(strict_types=1);

namespace App\Saml;

use App\Tenant\TenantFactory;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Saml\Bridge\Model\ModelEntityId;
use Beffroi\Saml\Domain\Metadata\ServiceProviderEndpoints;
use function is_string;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * beffroi/saml S-38
 *
 * A value that holds an environment variable is left to RouterServiceProviderEndpoints, which checks it at its
 * first use.
 */
final class ServiceProviderEntityIdPass implements CompilerPassInterface
{
    public const string PATH = 'beffroi.saml.service_provider.entity_id';

    /**
     * @throws InvalidConfigurationException
     */
    public function process(ContainerBuilder $container): void
    {
        $entityId = $container->getDefinition(RouterServiceProviderEndpoints::class)->getArgument('$entityId');
        if (! is_string($entityId) || self::holdsAnEnvironmentVariable($container, $entityId)) {
            return;
        }
        try {
            ModelEntityId::read($entityId);
            $issuer = $container->hasDefinition(TenantFactory::class) ? $container->getDefinition(TenantFactory::class)->getArgument('$issuer') : null;
            if (is_string($issuer) && ! self::holdsAnEnvironmentVariable($container, $issuer)) {
                ServiceProviderEndpoints::assertOwnEntityId($entityId, $issuer . IdentityProviderRoutes::METADATA_PATH);
            }
        } catch (InvalidArgumentException $refusal) {
            throw new InvalidConfigurationException('Invalid configuration for path "' . self::PATH . '": ' . $refusal->getMessage(), 0, $refusal);
        }
    }

    private static function holdsAnEnvironmentVariable(ContainerBuilder $container, string $value): bool
    {
        $environment = [];
        $container->resolveEnvPlaceholders($value, null, $environment);

        return $environment !== [];
    }
}
