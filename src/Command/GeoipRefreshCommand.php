<?php

declare(strict_types=1);

namespace App\Command;

use function array_filter;
use function array_values;
use function is_string;
use function sprintf;
use App\Configuration\GeoipSection;
use App\Geo\GeoLite2Refresh;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:geoip:refresh',
    description: 'Download the GeoLite2 databases of addresses now, refuse what is not one, and store them for the enrichment of a provenance; --url for a mirror or a file already unpacked.',
)]
final readonly class GeoipRefreshCommand
{
    public function __construct(
        private GeoLite2Refresh $refresh,
    ) {
    }

    /**
     * @param list<string> $url
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(
            description: 'The archive of one database, an https URL or a file, repeatable; the editions the enrichment reads, from MaxMind, unless given.',
        )]
        array $url = [],
    ): int {
        $refreshments = $this->refresh->refresh(array_values(array_filter($url, is_string(...))));
        if ($refreshments === []) {
            $io->warning(sprintf(
                'This deployment names no licence key, so there is nothing to download: write one under "beffroi.geoip.license_key" (%s in the standalone application), or name an archive with --url.',
                GeoipSection::LICENSE_KEY_ENVIRONMENT_VARIABLE,
            ));

            return Command::INVALID;
        }
        $refused = 0;
        foreach ($refreshments as $refreshment) {
            if ($refreshment->refused) {
                ++$refused;
                $io->error($refreshment->what);
                continue;
            }
            $io->success($refreshment->what);
        }

        return $refused === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
