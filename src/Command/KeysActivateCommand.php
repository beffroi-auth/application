<?php

declare(strict_types=1);

namespace App\Command;

use DateTimeInterface;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Key\KeyTakeOver;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Exception\LogicException;
use Beffroi\Core\Port\TenantKeyRepositoryInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:keys:activate',
    description: 'Make a staged key the active one of its algorithm before its staging delay elapsed; the key it takes over from starts retiring.',
)]
final readonly class KeysActivateCommand
{
    public const string NAME = 'beffroi:keys:activate';

    public function __construct(
        private ConfiguredTenant $tenant,
        private RetiringKey $retiring,
        private TenantKeyRepositoryInterface $keys,
        private KeyTakeOver $takeOver,
        private RequestContextFactory $contexts,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The identifier ("kid") of the staged key, as beffroi:keys:list shows it.')]
        string $kid,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $staged = $this->keys->find($tenant, $kid);
        $previous = $staged === null ? null : $this->keys->findActive($tenant, $staged->algorithm);

        try {
            $key = $this->takeOver->activate($tenant, $kid, $this->contexts->fromConsole(self::NAME));
        } catch (KeyNotFoundException|LogicException $exception) {
            $io->error(PrintedText::inBlock($exception->getMessage()));

            return Command::FAILURE;
        }

        $io->definitionList(
            [
                'Key identifier' => $key->kid,
            ],
            [
                'Algorithm' => $key->algorithm->name(),
            ],
            [
                'Use' => $key->algorithm->keyUse()->value,
            ],
            [
                'Status' => $key->status->value,
            ],
            [
                'Activated at' => $key->activatedAt?->format(DateTimeInterface::RFC3339) ?? '?',
            ],
        );
        if ($previous !== null) {
            $this->retiring->told($io, $tenant, $previous);
        }
        $io->success(sprintf('The key "%s" is the active %s key of "%s" from now on.', $key->kid, $key->algorithm->name(), $tenant->slug));

        return Command::SUCCESS;
    }
}
