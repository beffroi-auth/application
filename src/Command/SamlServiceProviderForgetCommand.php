<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Saml\Application\Metadata\AcceptedMetadataForgetting;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * beffroi/saml S-6, section 6.2
 */
#[AsCommand(
    name: self::NAME,
    description: 'Forget the metadata accepted for a service provider, so that the next refresh registers it again as a first one; the refresh refuses older documents only against what is accepted.',
)]
final readonly class SamlServiceProviderForgetCommand
{
    public const string NAME = 'beffroi:saml:service-provider:forget';

    public function __construct(
        private ConfiguredTenant $tenant,
        private AcceptedMetadataForgetting $forgetting,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The entity ID of the service provider, compared exactly, as "beffroi.saml.service_providers" declares it or declared it.')]
        string $entityId,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        if (! $this->forgetting->forget($tenant, $entityId)) {
            $io->error(sprintf('No metadata are accepted for the service provider "%s": nothing is forgotten.', $entityId));

            return Command::INVALID;
        }
        $io->success(sprintf(
            'The metadata accepted for the service provider "%s" are forgotten; "%s" registers it again as a first one.',
            $entityId,
            SamlServiceProviderRefreshCommand::NAME,
        ));

        return Command::SUCCESS;
    }
}
