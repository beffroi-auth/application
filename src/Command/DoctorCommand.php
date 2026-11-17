<?php

declare(strict_types=1);

namespace App\Command;

use App\Doctor\CheckInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'Say whether this deployment holds together: one line per point looked at, and what to do about the ones it does not answer for. Reads and writes nothing.',
)]
final readonly class DoctorCommand
{
    public const string NAME = 'beffroi:doctor';

    /**
     * @param iterable<CheckInterface> $checks
     */
    public function __construct(
        private iterable $checks,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $looked = 0;
        $red = 0;
        foreach ($this->checks as $check) {
            ++$looked;
            $finding = $check->finding();
            if (! $finding->green) {
                ++$red;
                $io->error([$finding->about, ...$finding->lines]);

                continue;
            }
            $io->writeln(sprintf(' <fg=green>[ok]</> %s', $finding->about));
            foreach ($finding->lines as $line) {
                $io->writeln(sprintf('      %s', $line));
            }
            $io->newLine();
        }

        if ($looked === 0) {
            $io->warning('Nothing is wired to look at this deployment: no service carries the tag "beffroi.doctor_check", so this run answers for nothing at all.');

            return Command::FAILURE;
        }

        if ($red > 0) {
            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d point%s looked at, and this deployment answers for %s.',
            $looked,
            $looked === 1 ? '' : 's',
            $looked === 1 ? 'it' : 'all of them',
        ));

        return Command::SUCCESS;
    }
}
