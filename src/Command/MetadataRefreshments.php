<?php

declare(strict_types=1);

namespace App\Command;

use Beffroi\Saml\Application\Metadata\MetadataRefreshment;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class MetadataRefreshments
{
    /**
     * @param list<MetadataRefreshment> $refreshments
     */
    public static function told(SymfonyStyle $io, array $refreshments): int
    {
        $refused = 0;
        foreach ($refreshments as $refreshment) {
            if ($refreshment->refused) {
                ++$refused;
                $io->error(PrintedText::inBlock($refreshment->what));
                continue;
            }
            $io->success(PrintedText::inBlock($refreshment->what));
        }

        return $refused === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
