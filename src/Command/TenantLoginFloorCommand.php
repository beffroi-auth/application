<?php

declare(strict_types=1);

namespace App\Command;

use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Tenant\TenantHardening;
use Beffroi\Core\Exception\InvalidArgumentException;
use Beffroi\Core\Port\AuthenticationPredicatesInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'Name the floor of every login of the tenant, and forget the credentials it forbids.',
)]
final readonly class TenantLoginFloorCommand
{
    public const string NAME = 'beffroi:tenant:login-floor';

    public function __construct(
        private ConfiguredTenant $tenant,
        private TenantHardening $hardening,
        private RequestContextFactory $contexts,
        private AuthenticationPredicatesInterface $predicates,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(
            description: 'The predicate every login must satisfy, by the name that predicate gives itself. The predicates are the ones this provider is wired with; a name none of them carries is refused with the ones that are.',
        )]
        string $floor,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        try {
            $named = $this->predicates->named($floor);
        } catch (InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }
        if ($named->name() === $tenant->loginFloor->name()) {
            $io->success(sprintf('Every login of the tenant "%s" already proves "%s" at the least.', $tenant->slug, $named->name()));

            return Command::SUCCESS;
        }
        $io->definitionList(
            [
                'Tenant' => $tenant->slug,
            ],
            [
                'Floor now' => $tenant->loginFloor->name(),
            ],
            [
                'Floor asked' => $named->name(),
            ],
        );
        if (! $io->confirm(sprintf(
            'Every credential of this tenant that proves nothing "%s" reads is forgotten, secret and row, and has to be posed again. Continue?',
            $named->name(),
        ), false)) {
            $io->warning('Nothing was changed.');

            return Command::INVALID;
        }

        $forgotten = $this->hardening->adopt($tenant, $named, $this->contexts->fromConsole(self::NAME));
        $io->success(sprintf(
            'Every login of the tenant "%s" proves "%s" at the least; %d credential%s forgotten.',
            $tenant->slug,
            $named->name(),
            $forgotten,
            $forgotten === 1 ? ' was' : 's were',
        ));

        return Command::SUCCESS;
    }
}
