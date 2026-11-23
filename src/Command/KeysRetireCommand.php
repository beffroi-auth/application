<?php

declare(strict_types=1);

namespace App\Command;

use function count;
use DateTimeInterface;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Key\KeyRetirement;
use Beffroi\Core\Domain\Key\TenantKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Exception\LogicException;
use Beffroi\Core\Exception\RetirementNotDueException;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:keys:retire',
    description: 'Take a retiring key out of the JWK Set: one key by its identifier, before its retirement date with --force; or every key past its date with --due.',
)]
final readonly class KeysRetireCommand
{
    public const string NAME = 'beffroi:keys:retire';

    public function __construct(
        private ConfiguredTenant $tenant,
        private KeyRetirement $retirement,
        private RequestContextFactory $contexts,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier ("kid") of the retiring key, as beffroi:keys:list shows it.')]
        ?string $kid = null,
        #[Option(
            description: 'Retire the key although what is bound to it may still arrive: after a compromise, or when everything it is bound to is known to have expired.',
        )]
        bool $force = false,
        #[Option(description: 'Retire every key past its retirement date, and no other: what the scheduled step does.')]
        bool $due = false,
    ): int {
        if ($due === ($kid !== null)) {
            $io->error('Name the key to retire, or pass --due to retire every key past its retirement date; not both, not neither.');

            return Command::INVALID;
        }

        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        $context = $this->contexts->fromConsole(self::NAME);

        if ($kid === null) {
            $retired = $this->retirement->retireDue($tenant, $context);
            if ($retired === []) {
                $io->note(sprintf('No key of "%s" is past its retirement date.', $tenant->slug));

                return Command::SUCCESS;
            }
            $io->listing(array_map(static fn (TenantKey $key): string => sprintf(
                '%s, due since %s',
                $key->kid,
                $key->retirementDueAt?->format(DateTimeInterface::RFC3339) ?? '?',
            ), $retired));
            $io->success(sprintf('%d key(s) of "%s" left the JWK Set.', count($retired), $tenant->slug));

            return Command::SUCCESS;
        }

        try {
            $key = $force
                ? $this->retirement->retireBeforeItsDate($tenant, $kid, $context)
                : $this->retirement->retire($tenant, $kid, $context);
        } catch (RetirementNotDueException $exception) {
            $io->error([PrintedText::inBlock($exception->getMessage()), 'Wait for the date, or pass --force if nothing bound to it can arrive any more.']);

            return Command::FAILURE;
        } catch (KeyNotFoundException|LogicException $exception) {
            $io->error(PrintedText::inBlock($exception->getMessage()));

            return Command::FAILURE;
        }

        return self::retired($io, $tenant, $key, $force);
    }

    private static function retired(SymfonyStyle $io, Tenant $tenant, TenantKey $key, bool $force): int
    {
        $io->definitionList(
            [
                'Key identifier' => $key->kid,
            ],
            [
                'Status' => $key->status->value,
            ],
            [
                'Retired at' => $key->retiredAt?->format(DateTimeInterface::RFC3339) ?? '?',
            ],
        );
        if ($force) {
            $io->warning(sprintf('The key "%s" left the JWK Set before its retirement date: what is bound to it is refused from now on.', $key->kid));
        }
        $io->success(sprintf('The key "%s" of "%s" left the JWK Set.', $key->kid, $tenant->slug));

        return Command::SUCCESS;
    }
}
