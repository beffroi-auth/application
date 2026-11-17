<?php

declare(strict_types=1);

namespace App\Command;

use function array_keys;
use function count;
use App\Doctrine\PersonalData\TenantDataKeyDestruction;
use App\Doctrine\PersonalData\TenantDataKeys;
use function implode;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:data-keys:destroy',
    description: 'Erase the data keys of the tenant, which makes every personal datum it had sealed unreadable for good. Asks for the slug to be typed back. Cannot be undone.',
)]
final readonly class DataKeysDestroyCommand
{
    public const string NAME = 'beffroi:data-keys:destroy';

    public function __construct(
        private ConfiguredTenant $tenant,
        private TenantDataKeys $keys,
        private TenantDataKeyDestruction $destruction,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The slug of the tenant, typed back: what this erases cannot be put back.')]
        ?string $slug = null,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $scopes = $this->keys->of($tenant);
        if ($scopes === []) {
            $io->warning(sprintf('The tenant "%s" holds no data key: there is nothing to erase.', $tenant->slug));

            return Command::SUCCESS;
        }

        if ($slug !== $tenant->slug) {
            $io->error([
                $slug === null
                    ? sprintf('Type the slug of the tenant back to erase its data keys: "%s %s".', self::NAME, $tenant->slug)
                    : sprintf('"%s" is not the slug of the tenant this deployment is configured for, which is "%s".', $slug, $tenant->slug),
                sprintf(
                    'It holds %d key(s) over %d scope(s): %s. Erasing them makes every personal datum sealed under them unreadable for good, and nothing puts them back.',
                    $this->keys->countOf($tenant),
                    count($scopes),
                    implode(', ', array_keys($scopes)),
                ),
            ]);

            return Command::INVALID;
        }

        $erased = $this->destruction->destroy($tenant, self::NAME);
        $io->success(sprintf(
            '%d data key(s) of the tenant "%s" erased. Every column they sealed holds a ciphertext nothing opens, and the rows are still there: what is gone is the means of reading them.',
            $erased,
            $tenant->slug,
        ));

        return Command::SUCCESS;
    }
}
