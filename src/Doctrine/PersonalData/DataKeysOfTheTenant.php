<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use Beffroi\Core\Bridge\KeyManagement\KeyManagementFailure;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Core\Port\OpenableKeysInterface;
use function sprintf;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Exception\RuntimeException as KeyManagementRuntimeException;

/**
 * Every data key of the tenant, retired ones included, since nothing seals a value again under a newer key; the key of
 * the blind indexes through the store its tags are derived with.
 */
final readonly class DataKeysOfTheTenant implements OpenableKeysInterface
{
    public function __construct(
        private TenantDataKeys $keys,
        private DataKeyStoreInterface $store,
        private TenantBlindIndexes $indexes,
    ) {
    }

    public function open(Tenant $tenant): void
    {
        $index = TenantScopedEncrypter::scope($tenant->slug, BlindIndexedColumns::SCOPE);
        try {
            $this->indexes->open($tenant);
            foreach ($this->keys->of($tenant) as $scope => $keys) {
                if ($scope === $index) {
                    continue;
                }
                foreach ($keys as $key) {
                    $this->store->get($key->reference);
                }
            }
        } catch (KeyManagementRuntimeException $exception) {
            throw KeyManagementFailure::of($exception, sprintf('A data key of the tenant "%s" could not be opened.', $tenant->slug));
        }
    }
}
