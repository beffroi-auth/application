<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantSynchronizer;
use function implode;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:tenant:synchronize',
    description: 'Write the row of the tenant of the configuration: created, renamed, or left alone.',
)]
final readonly class TenantSynchronizeCommand
{
    public function __construct(
        private TenantSynchronizer $synchronizer,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $tenant = $this->synchronizer->synchronize();
        } catch (TenantNotSynchronizedException $exception) {
            $io->error(PrintedText::inBlock($exception->getMessage()));

            return Command::FAILURE;
        }

        $io->definitionList(
            [
                'Slug' => $tenant->slug,
            ],
            [
                'Issuer' => (string) $tenant->issuer,
            ],
            [
                'Name' => $tenant->name,
            ],
            [
                'Identifier' => $tenant->id->toRfc4122(),
            ],
            [
                'Logo' => $tenant->look->logoUri->value ?? '',
            ],
            [
                'Changeable' => $tenant->selfServedClaims->names === [] ? 'nothing' : implode(', ', $tenant->selfServedClaims->names),
            ],
            [
                'Colours' => $tenant->look->primaryColor === null
                    ? ''
                    : sprintf('%s on %s', $tenant->look->primaryColor, (string) $tenant->look->primaryContentColor),
            ],
        );
        $io->success(sprintf('The tenant "%s" is synchronised with the configuration.', $tenant->slug));

        return Command::SUCCESS;
    }
}
