<?php

declare(strict_types=1);

namespace App\Command;

use function count;
use App\Doctrine\PersonalData\TenantDataKeys;
use App\Doctrine\PersonalData\TenantScopedDataKeyStore;
use App\Http\RequestContextFactory;
use Beffroi\Core\Bridge\KeyManagement\KmsKeyId;
use Beffroi\Core\Domain\Event\TenantDataKeysRewrapped;
use function implode;
use function iterator_count;
use Psr\EventDispatcher\EventDispatcherInterface;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[AsCommand(
    name: 'beffroi:data-keys:rewrap',
    description: 'Wrap the data keys of the tenant with another KMS client from now on, or with another master key of the same one; --dry-run lists what would move. No sealed value is read or rewritten.',
)]
final readonly class DataKeysRewrapCommand
{
    public const string NAME = 'beffroi:data-keys:rewrap';

    /**
     * @param ServiceProviderInterface<DecrypterInterface&EncrypterInterface> $clients keyed by their configured name
     * @param string $masterKeyId a pattern, "{tenant}" standing for the slug of the tenant
     */
    public function __construct(
        private ConfiguredTenant $tenant,
        private RewrappableDataKeyStoreInterface $store,
        private TenantDataKeys $keys,
        private ServiceProviderInterface $clients,
        private EventDispatcherInterface $events,
        private RequestContextFactory $contexts,
        private string $masterKeyId,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Name of the KMS client that must wrap the data keys of this tenant from now on.')]
        ?string $to = null,
        #[Option(description: 'Only move the keys this client holds today; all of them when omitted, which is what a run after a change of client looks like.')]
        ?string $from = null,
        #[Option(description: 'List what would move and write nothing.')]
        bool $dryRun = false,
        #[Option(description: 'Also move the keys wrapped under no context, written before the store bound each one to its reference and its scope.')]
        bool $acceptUnbound = false,
    ): int {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }
        if ($to === null) {
            $io->error(sprintf(
                'Name the KMS client that must wrap the data keys from now on with --to. The clients of this deployment are %s.',
                implode(', ', self::names($this->clients)),
            ));

            return Command::INVALID;
        }

        $scoped = new TenantScopedDataKeyStore($this->store, $tenant);
        $moving = iterator_count($scoped->all($from));
        if ($moving === 0) {
            $io->warning(sprintf(
                'The tenant "%s" holds no data key%s: there is nothing to move. "beffroi:data-keys:list" says what it holds.',
                $tenant->slug,
                $from === null ? '' : sprintf(' wrapped by "%s"', $from),
            ));

            return Command::SUCCESS;
        }

        $masterKeyId = new KmsKeyId($this->masterKeyId)->forSlug($tenant->slug);
        $io->note(sprintf('The master key of "%s" on "%s" is "%s", resolved from the configuration and not asked for.', $tenant->slug, $to, $masterKeyId));

        $status = new RewrapDataKeysCommand($scoped, $this->clients)->__invoke(
            $io,
            to: $to,
            keyId: $masterKeyId,
            from: $from,
            dryRun: $dryRun,
            acceptUnbound: $acceptUnbound,
        );
        if ($status !== Command::SUCCESS || $dryRun) {
            return $status;
        }

        $context = $this->contexts->fromConsole(self::NAME);
        $this->events->dispatch(new TenantDataKeysRewrapped(
            $tenant,
            $moving,
            count($this->keys->of($tenant)),
            $from,
            $to,
            $masterKeyId,
            $context->receivedAt,
            $context,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param ServiceProviderInterface<DecrypterInterface&EncrypterInterface> $clients
     *
     * @return list<string>
     */
    private static function names(ServiceProviderInterface $clients): array
    {
        $names = [];
        foreach ($clients->getProvidedServices() as $name => $type) {
            $names[] = (string) $name;
        }

        return $names;
    }
}
