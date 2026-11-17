<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Saml\Bridge\Configuration\ConfigurationServiceProviders;
use Beffroi\Saml\Port\ServedClaimsInterface;
use function is_array;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Builds the declared service providers at compile time, so that a declaration that does not hold together
 * fails the build rather than its first use; MappedClaimsWarmer checks the claims they map.
 */
final class ServiceProvidersPass implements CompilerPassInterface
{
    public const string PATH = 'beffroi.saml.service_providers';

    /**
     * @param array<array-key, mixed> $declarations by entity ID
     *
     * @throws InvalidConfigurationException
     */
    public static function build(array $declarations, ServedClaimsInterface $claims): void
    {
        try {
            new ConfigurationServiceProviders('', $declarations, $claims);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigurationException(sprintf('Invalid configuration for path "%s": %s', self::PATH, $exception->getMessage()), 0, $exception);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(ConfigurationServiceProviders::class)) {
            return;
        }
        $declarations = $container->getDefinition(ConfigurationServiceProviders::class)->getArgument('$definitions');
        if (! is_array($declarations)) {
            return;
        }
        $literal = LiteralDeclarations::of($container, $declarations);
        self::build($literal, DeclaredClaims::mappedBy($literal));
        $container->getDefinition(MappedClaimsWarmer::class)->setArgument('$declarations', $literal);
    }
}
