<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use DateTimeImmutable;
use function array_keys;
use function count;
use Beffroi\Core\Domain\Tenant\Tenant;
use function ksort;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final readonly class TenantDataKeys
{
    public function __construct(
        private RewrappableDataKeyStoreInterface $store,
    ) {
    }

    /**
     * @return array<string, list<StoredDataKeyDescription>> by scope, alphabetical, each list oldest first
     */
    public function of(Tenant $tenant): array
    {
        $scopes = [];
        foreach ($this->store->all() as $stored) {
            if (TenantScopedEncrypter::slugOf($stored->scope) !== $tenant->slug) {
                continue;
            }
            $scopes[$stored->scope][] = new StoredDataKeyDescription(
                $stored->reference,
                self::mintedAt($stored->reference),
                $stored->wrapped->keyId,
                $stored->client,
            );
        }
        ksort($scopes);

        return $scopes;
    }

    public function countOf(Tenant $tenant): int
    {
        $total = 0;
        foreach ($this->of($tenant) as $keys) {
            $total += count($keys);
        }

        return $total;
    }

    /**
     * @return list<string> in alphabetical order
     */
    public function scopesOf(Tenant $tenant): array
    {
        return array_keys($this->of($tenant));
    }

    private static function mintedAt(string $reference): ?DateTimeImmutable
    {
        if (! Uuid::isValid($reference, Uuid::FORMAT_BINARY)) {
            return null;
        }
        $uuid = Uuid::fromBinary($reference);

        return $uuid instanceof UuidV7 ? $uuid->getDateTime() : null;
    }
}
