<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use Beffroi\Core\Domain\Tenant\Tenant;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;

/**
 * Scopes all() to one tenant for key-management:rewrap-data-keys; every other method goes straight through.
 */
final readonly class TenantScopedDataKeyStore implements RewrappableDataKeyStoreInterface
{
    public function __construct(
        private RewrappableDataKeyStoreInterface $store,
        private Tenant $tenant,
    ) {
    }

    public function all(?string $client = null): iterable
    {
        foreach ($this->store->all($client) as $stored) {
            if (TenantScopedEncrypter::slugOf($stored->scope) === $this->tenant->slug) {
                yield $stored;
            }
        }
    }

    public function current(string $scope): DataKeyHandle
    {
        return $this->store->current($scope);
    }

    public function rotate(string $scope): DataKeyHandle
    {
        return $this->store->rotate($scope);
    }

    public function get(string $reference): DataKeyHandle
    {
        return $this->store->get($reference);
    }

    public function rewrap(StoredDataKey $row, DataKey $rewrapped, string $client): void
    {
        $this->store->rewrap($row, $rewrapped, $client);
    }

    public function wrappingContextOf(StoredDataKey $row): string
    {
        return $this->store->wrappingContextOf($row);
    }
}
