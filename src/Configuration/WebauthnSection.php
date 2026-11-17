<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Webauthn\FileAuthenticatorListStore;
use App\Webauthn\FileMetadataBlobStore;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

final readonly class WebauthnSection extends ConfigurationSection
{
    public function name(): string
    {
        return 'webauthn';
    }

    public function describe(NodeBuilder $children): void
    {
        $children
            ->arrayNode('webauthn')
            ->info('What the passkey ceremonies and the pages that name the authenticator of a passkey read besides the tenant (architecture §3.4).')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('metadata_storage')
            ->info('Directory of the FIDO metadata the ceremonies check an attestation against: the documents "beffroi:webauthn:mds-refresh" verified and "beffroi:webauthn:mds-import" stored, next to the keys; empty until one of them ran, which the enrolment of the provider does not need. Its "community" directory holds the list of passkey providers "beffroi:webauthn:aaguids-refresh" stored, which the pages name the authenticator of a passkey from when the metadata do not.')
            ->defaultValue('%kernel.project_dir%/var/webauthn')
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end();
    }

    public function load(array $config, ServicesConfigurator $services, bool $debug): void
    {
        $storage = self::string($this->own($config), 'metadata_storage');
        $services->get(FileMetadataBlobStore::class)->arg('$directory', $storage);
        $services->get(FileAuthenticatorListStore::class)->arg('$directory', $storage . '/community');
    }
}
