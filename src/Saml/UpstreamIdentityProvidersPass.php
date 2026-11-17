<?php

declare(strict_types=1);

namespace App\Saml;

use Beffroi\Core\Exception\InvalidArgumentException;
use App\Tenant\TenantFactory;
use Beffroi\Saml\Bridge\Configuration\ConfigurationUpstreamIdentityProviders;
use Beffroi\Saml\Domain\Upstream\UpstreamIdentityProvider;
use function array_keys;
use Beffroi\Saml\Port\ProfileClaimsInterface;
use function is_array;
use function is_string;
use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Builds the declared upstream identity providers at compile time, so that a declaration that does not hold
 * together fails the build rather than its first use; MappedClaimsWarmer checks the claims they name.
 */
final class UpstreamIdentityProvidersPass implements CompilerPassInterface
{
    public const string PATH = 'beffroi.saml.identity_providers';

    /**
     * @param array<array-key, mixed> $declarations by entity ID
     *
     * @throws InvalidConfigurationException when a declaration does not hold together, or two declare one domain
     */
    public static function build(array $declarations, ProfileClaimsInterface $claims): void
    {
        try {
            new ConfigurationUpstreamIdentityProviders('', $declarations, $claims);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigurationException(sprintf('Invalid configuration for path "%s": %s', self::PATH, $exception->getMessage()), 0, $exception);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(ConfigurationUpstreamIdentityProviders::class)) {
            return;
        }
        $declarations = $container->getDefinition(ConfigurationUpstreamIdentityProviders::class)->getArgument('$definitions');
        if (! is_array($declarations)) {
            return;
        }
        $literal = LiteralDeclarations::of($container, $declarations);
        self::build($literal, DeclaredClaims::filledBy($literal));
        self::refuseTheTenant($container, array_keys($literal));
        $container->getDefinition(MappedClaimsWarmer::class)->setArgument('$upstreamDeclarations', $literal);
    }

    /**
     * beffroi/saml S-42
     *
     * @param list<array-key> $entityIds
     *
     * @throws InvalidConfigurationException
     */
    private static function refuseTheTenant(ContainerBuilder $container, array $entityIds): void
    {
        $issuer = self::literal(
            $container,
            $container->hasDefinition(TenantFactory::class) ? $container->getDefinition(TenantFactory::class)->getArgument('$issuer') : null,
        );
        $named = $container->getDefinition(RouterServiceProviderEndpoints::class)->getArgument('$entityId');
        self::refuseTheTenantUnder(
            $entityIds,
            $issuer,
            $named === null ? self::under($issuer, ServiceProviderRoutes::METADATA_PATH) : self::literal($container, $named),
        );
    }

    /**
     * Applies beffroi/saml S-42 at run time, to a list the environment carries (SamlSection).
     *
     * @param list<array-key> $entityIds
     * @param string|null $namedServiceProvider null for the URL of its metadata
     *
     * @throws InvalidConfigurationException
     */
    public static function refuseTheTenantAt(array $entityIds, string $issuer, ?string $namedServiceProvider): void
    {
        self::refuseTheTenantUnder($entityIds, $issuer, $namedServiceProvider ?? self::under($issuer, ServiceProviderRoutes::METADATA_PATH));
    }

    /**
     * @param list<array-key> $entityIds
     *
     * @throws InvalidConfigurationException
     */
    private static function refuseTheTenantUnder(array $entityIds, ?string $issuer, ?string $serviceProvider): void
    {
        $identityProvider = self::under($issuer, IdentityProviderRoutes::METADATA_PATH);
        try {
            foreach ($entityIds as $entityId) {
                UpstreamIdentityProvider::assertNotTheTenant((string) $entityId, $identityProvider, $serviceProvider);
            }
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigurationException(sprintf('Invalid configuration for path "%s": %s', self::PATH, $exception->getMessage()), 0, $exception);
        }
    }

    private static function under(?string $issuer, string $path): ?string
    {
        return $issuer === null ? null : $issuer . $path;
    }

    private static function literal(ContainerBuilder $container, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $environment = [];
        $container->resolveEnvPlaceholders($value, null, $environment);

        return $environment === [] ? $value : null;
    }
}
