<?php

declare(strict_types=1);

namespace App\Command;

use function array_map;
use App\Saml\RouterServiceProviderEndpoints;
use Beffroi\Saml\Domain\Upstream\UpstreamIdentityProvider;
use Beffroi\Saml\Domain\Key\SamlEncryptionAlgorithmInterface;
use Beffroi\Saml\Port\UpstreamIdentityProvidersInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * beffroi/saml S-65, section 5.7
 */
#[AsCommand(
    name: self::NAME,
    description: 'List the upstream identity providers the configuration declares, each with the URL of the metadata of the service provider to hand it.',
)]
final readonly class SamlIdentityProviderListCommand
{
    public const string NAME = 'beffroi:saml:identity-provider:list';

    public function __construct(
        private ConfiguredTenant $tenant,
        private UpstreamIdentityProvidersInterface $identityProviders,
        private RouterServiceProviderEndpoints $endpoints,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $identityProviders = $this->identityProviders->of($tenant);
        if ($identityProviders === []) {
            $io->warning('The tenant declares no identity provider: declare one under "beffroi.saml.identity_providers".');

            return Command::INVALID;
        }
        $io->table(
            ['Identity provider', 'Metadata URL'],
            array_map(fn (UpstreamIdentityProvider $identityProvider): array => [
                $identityProvider->entityId,
                $this->endpoints->metadataFor($tenant, $identityProvider->entityId),
            ], $identityProviders),
        );
        $io->note(sprintf(
            'A URL answers once the tenant holds a key in service for its provider: "%s --algorithm=%s<entity ID>".',
            KeysGenerateCommand::NAME,
            SamlEncryptionAlgorithmInterface::NAME_PREFIX,
        ));

        return Command::SUCCESS;
    }
}
