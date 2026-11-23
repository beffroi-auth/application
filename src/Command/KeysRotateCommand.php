<?php

declare(strict_types=1);

namespace App\Command;

use DateTimeInterface;
use App\Http\RequestContextFactory;
use Beffroi\Core\Application\Key\KeyStaging;
use Beffroi\Core\Application\Key\KeyTakeOver;
use Beffroi\Core\Domain\Key\TenantKey;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Exception\UnsupportedAlgorithmException;
use Beffroi\Core\Port\KeyAlgorithmsInterface;
use Beffroi\Core\Port\SignatureAlgorithmsInterface;
use Beffroi\Core\Port\TenantKeyRepositoryInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:keys:rotate',
    description: 'Stage a new key, sealed by the configured KMS and published at once; it takes over once the staging delay elapsed, or at once with --now; --compromised retires the previous key, and every key waiting to take over, with no window.',
)]
final readonly class KeysRotateCommand
{
    public const string NAME = 'beffroi:keys:rotate';

    public function __construct(
        private ConfiguredTenant $tenant,
        private RetiringKey $retiring,
        private TenantKeyRepositoryInterface $keys,
        private KeyStaging $staging,
        private KeyTakeOver $takeOver,
        private RequestContextFactory $contexts,
        private SignatureAlgorithmsInterface $signatureAlgorithms,
        private KeyAlgorithmsInterface $algorithms,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: KeysGenerateCommand::ALGORITHM_OPTION)]
        ?string $algorithm = null,
        #[Option(
            description: 'Activate the key at once, without the staging delay: a relying party whose JWK Set copy is younger than 60 s refuses the new tokens, or encrypts to the old key, until it is that old (oidc_login refetches once then); for an operator who knows every relying party is cold.',
        )]
        bool $now = false,
        #[Option(
            description: 'The active key is compromised: the new key takes over at once, and the compromised one and every key of its algorithm waiting to take over leave the JWK Set with no window.',
        )]
        bool $compromised = false,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        try {
            $rotatedFor = $algorithm === null ? $this->signatureAlgorithms->defaultAlgorithm() : $this->algorithms->named($algorithm);
        } catch (UnsupportedAlgorithmException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $previous = $this->keys->findActive($tenant, $rotatedFor);
        $context = $this->contexts->fromConsole(self::NAME);
        if ($previous === null) {
            $key = $compromised
                ? $this->takeOver->rotateAfterCompromise($tenant, $rotatedFor, $context)
                : $this->takeOver->rotate($tenant, $rotatedFor, $context);
            self::describe($io, $tenant, $key);
            $io->note(sprintf('The tenant had no active %s key: nothing to wait for, the key is active at once.', $rotatedFor->name()));
        } elseif ($compromised) {
            $key = $this->takeOver->rotateAfterCompromise($tenant, $rotatedFor, $context);
            self::describe($io, $tenant, $key);
            $io->warning(sprintf(
                'The key "%s" was active and is retired at once: it leaves the JWK Set and nothing bound to it is honoured from now on.',
                $previous->kid,
            ));
        } elseif ($now) {
            $key = $this->takeOver->rotate($tenant, $rotatedFor, $context);
            self::describe($io, $tenant, $key);
            $this->retiring->told($io, $tenant, $previous);
        } else {
            $key = $this->staging->stage($tenant, $rotatedFor, $context);
            self::describe($io, $tenant, $key);
            $io->note(sprintf(
                'The key "%s" is the active one until then; the scheduled step activates the new key at %s, "beffroi:keys:activate %s" does it earlier.',
                $previous->kid,
                $key->activationDueAt->format(DateTimeInterface::RFC3339),
                $key->kid,
            ));
            $io->success(sprintf(
                'The key "%s" is published for "%s" and takes over at %s.',
                $key->kid,
                $tenant->slug,
                $key->activationDueAt->format(DateTimeInterface::RFC3339),
            ));

            return Command::SUCCESS;
        }
        $io->success(sprintf('The key "%s" is the active %s key of "%s" from now on.', $key->kid, $key->algorithm->name(), $tenant->slug));

        return Command::SUCCESS;
    }

    private static function describe(SymfonyStyle $io, Tenant $tenant, TenantKey $key): void
    {
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
                'Tenant' => $tenant->slug,
            ],
        );
    }
}
