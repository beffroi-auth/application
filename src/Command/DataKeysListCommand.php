<?php

declare(strict_types=1);

namespace App\Command;

use function count;
use DateTimeInterface;
use App\Doctrine\PersonalData\EncryptedColumnTypes;
use App\Doctrine\PersonalData\StoredDataKeyDescription;
use App\Doctrine\PersonalData\TenantDataKeys;
use function implode;
use function sprintf;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'beffroi:data-keys:list',
    description: 'List the data keys of the tenant by scope, with when each was minted, the master key that wraps it and the KMS client that opens it; reads only, and never opens a key.',
)]
final readonly class DataKeysListCommand
{
    public function __construct(
        private ConfiguredTenant $tenant,
        private TenantDataKeys $keys,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tenant = $this->tenant->orTold($io);
        if ($tenant === null) {
            return Command::FAILURE;
        }

        $scopes = $this->keys->of($tenant);
        if ($scopes === []) {
            $io->warning(sprintf(
                'The tenant "%s" holds no data key: a key is minted at the first write of its family, and nothing has been sealed yet. The families are %s.',
                $tenant->slug,
                implode(', ', EncryptedColumnTypes::families()),
            ));

            return Command::SUCCESS;
        }

        foreach ($scopes as $scope => $keys) {
            $io->section($scope);
            $io->table(
                ['Reference', 'Minted', 'Master key', 'KMS client', ''],
                self::rows($keys),
            );
        }
        $io->success(sprintf(
            '%d data key(s) over %d scope(s) for the tenant "%s". The last key of a scope is the one a write uses; the others open what they sealed and are never erased by a rotation.',
            $this->keys->countOf($tenant),
            count($scopes),
            $tenant->slug,
        ));

        return Command::SUCCESS;
    }

    /**
     * The last row is marked: DataKeyStore::current() takes the greatest reference, and a reference is a UUIDv7.
     *
     * @param list<StoredDataKeyDescription> $keys
     *
     * @return list<list<string>>
     */
    private static function rows(array $keys): array
    {
        $rows = [];
        $last = count($keys) - 1;
        foreach ($keys as $index => $key) {
            $rows[] = [
                $key->hex(),
                $key->mintedAt?->format(DateTimeInterface::ATOM) ?? 'unknown',
                $key->masterKeyId,
                $key->client,
                $index === $last ? 'current' : 'retired',
            ];
        }

        return $rows;
    }
}
