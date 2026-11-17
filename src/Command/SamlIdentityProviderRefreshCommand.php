<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Saml\Application\Metadata\MetadataRefresh;
use Beffroi\Saml\Domain\Upstream\UpstreamIdentityProvider;
use Beffroi\Saml\Port\UpstreamIdentityProvidersInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * beffroi/saml section 6.10, S-42
 */
#[AsCommand(
    name: self::NAME,
    description: 'Read the metadata of the upstream identity providers the configuration declares, or of one of them, and keep each document accepted; an expired or an older document is refused and the one accepted kept.',
)]
final readonly class SamlIdentityProviderRefreshCommand
{
    public const string NAME = 'beffroi:saml:identity-provider:refresh';

    /**
     * @param MetadataRefresh<UpstreamIdentityProvider> $refresh
     */
    public function __construct(
        private ConfiguredTenant $tenant,
        private UpstreamIdentityProvidersInterface $identityProviders,
        private MetadataRefresh $refresh,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The entity ID of one identity provider, as "beffroi.saml.identity_providers" declares it; all of them, unless given.')]
        ?string $entityId = null,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        if ($entityId === null) {
            $refreshments = $this->refresh->refreshAll($tenant);
        } else {
            $identityProvider = $this->identityProviders->find($tenant, $entityId);
            if (! $identityProvider instanceof UpstreamIdentityProvider) {
                $io->error(sprintf('The tenant declares no identity provider "%s"; declare it under "beffroi.saml.identity_providers" first.', $entityId));

                return Command::INVALID;
            }
            $refreshments = [$this->refresh->refresh($tenant, $identityProvider)];
        }
        if ($refreshments === []) {
            $io->warning('The tenant declares no identity provider: declare one under "beffroi.saml.identity_providers".');

            return Command::INVALID;
        }

        return MetadataRefreshments::told($io, $refreshments);
    }
}
