<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Saml\Application\Metadata\MetadataRefresh;
use Beffroi\Saml\Domain\ServiceProvider\ServiceProvider;
use Beffroi\Saml\Port\ServiceProvidersInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * beffroi/saml section 6.2, S-5
 */
#[AsCommand(
    name: self::NAME,
    description: 'Read the metadata of the service providers the configuration declares, or of one of them, and keep each document accepted; an expired or an older document is refused and the one accepted kept.',
)]
final readonly class SamlServiceProviderRefreshCommand
{
    public const string NAME = 'beffroi:saml:service-provider:refresh';

    /**
     * @param MetadataRefresh<ServiceProvider> $refresh
     */
    public function __construct(
        private ConfiguredTenant $tenant,
        private ServiceProvidersInterface $serviceProviders,
        private MetadataRefresh $refresh,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The entity ID of one service provider, as "beffroi.saml.service_providers" declares it; all of them, unless given.')]
        ?string $entityId = null,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        if ($entityId === null) {
            $refreshments = $this->refresh->refreshAll($tenant);
        } else {
            $serviceProvider = $this->serviceProviders->find($tenant, $entityId);
            if (! $serviceProvider instanceof ServiceProvider) {
                $io->error(sprintf('The tenant declares no service provider "%s"; declare it under "beffroi.saml.service_providers" first.', $entityId));

                return Command::INVALID;
            }
            $refreshments = [$this->refresh->refresh($tenant, $serviceProvider)];
        }
        if ($refreshments === []) {
            $io->warning('The tenant declares no service provider: declare one under "beffroi.saml.service_providers".');

            return Command::INVALID;
        }
        return MetadataRefreshments::told($io, $refreshments);
    }
}
