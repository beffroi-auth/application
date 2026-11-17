<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Beffroi\Core\Bridge\KeyManagement\KeyManagementFailure;
use Beffroi\Core\Bridge\KeyManagement\KmsKeyId;
use Beffroi\Core\Exception\KmsUnavailableException;
use function is_string;
use Psr\Container\ContainerInterface;
use SensitiveParameter;
use function sprintf;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\RuntimeException as KeyManagementRuntimeException;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;

/**
 * One store per tenant, which opens the keys of its tenant whether a value is sealed or read, so that a key is asked of
 * the KMS once; one reader for the rows of every tenant, and for a reference that names no row.
 */
final class TenantDataKeyStores implements RewrappableDataKeyStoreInterface
{
    public const string TABLE = 'beffroi_data_key';

    private const int KEY_BYTES = 32;

    /**
     * @var array<string, DataKeyStore> by tenant slug
     */
    private array $stores = [];

    /**
     * @var array<string, string> the slug of the tenant of each reference, by reference
     */
    private array $slugs = [];

    private ?DataKeyStore $reader = null;

    /**
     * @param ContainerInterface $clients the KMS clients, by configured name
     * @param string $masterKeyId a pattern, "{tenant}" standing for the slug
     * @param non-empty-list<string> $secrets the first writes, every one reads
     * @param int<0, max>|null $maxAgeSeconds null for never
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ContainerInterface $clients,
        private readonly string $client,
        private readonly string $masterKeyId,
        #[SensitiveParameter]
        private readonly array $secrets,
        private readonly string $table,
        private readonly ?int $maxAgeSeconds = DataKeyStore::DEFAULT_MAX_AGE_SECONDS,
    ) {
    }

    /**
     * @throws KmsUnavailableException
     */
    public function current(string $scope): DataKeyHandle
    {
        try {
            return $this->of($scope)->current($scope);
        } catch (KeyManagementRuntimeException $exception) {
            throw KeyManagementFailure::outageOf($exception, sprintf('The data key of the scope "%s" could not be opened or minted.', $scope)) ?? $exception;
        }
    }

    /**
     * @throws KmsUnavailableException
     */
    public function rotate(string $scope): DataKeyHandle
    {
        try {
            return $this->of($scope)->rotate($scope);
        } catch (KeyManagementRuntimeException $exception) {
            throw KeyManagementFailure::outageOf($exception, sprintf('No new data key could be minted for the scope "%s".', $scope)) ?? $exception;
        }
    }

    /**
     * @throws KmsUnavailableException
     */
    public function get(string $reference): DataKeyHandle
    {
        try {
            return $this->storeOf($reference)->get($reference);
        } catch (KeyManagementRuntimeException $exception) {
            throw KeyManagementFailure::outageOf($exception, 'The data key of a sealed value could not be opened.') ?? $exception;
        }
    }

    public function all(?string $client = null): iterable
    {
        return $this->reader()->all($client);
    }

    public function rewrap(StoredDataKey $row, DataKey $rewrapped, string $client): void
    {
        $this->reader()->rewrap($row, $rewrapped, $client);
    }

    public function wrappingContextOf(StoredDataKey $row): string
    {
        return $this->reader()->wrappingContextOf($row);
    }

    private function of(string $scope): DataKeyStore
    {
        return $this->ofTenant(TenantScopedEncrypter::slugOf($scope));
    }

    private function ofTenant(string $slug): DataKeyStore
    {
        return $this->stores[$slug] ??= $this->storeUnder(new KmsKeyId($this->masterKeyId)->forSlug($slug));
    }

    /**
     * The store of the tenant its row names, read once per reference.
     */
    private function storeOf(string $reference): DataKeyStore
    {
        $slug = $this->slugs[$reference] ?? null;
        if ($slug === null) {
            $scope = $this->connection->fetchOne(sprintf('SELECT scope FROM %s WHERE id = ?', $this->table), [$reference], [ParameterType::BINARY]);
            if (! is_string($scope)) {
                return $this->reader();
            }
            $slug = $this->slugs[$reference] = TenantScopedEncrypter::slugOf($scope);
        }

        return $this->ofTenant($slug);
    }

    /**
     * Its master key is the pattern unresolved, which no read uses. Public, because the listener of the ORM bridge
     * declares the table through a DataKeyStore.
     */
    public function reader(): DataKeyStore
    {
        return $this->reader ??= $this->storeUnder($this->masterKeyId);
    }

    /**
     * The KMS authenticates the reference and the scope of every key it wraps: a key presented under another row is
     * refused before it leaves the KMS, and a backend that cannot authenticate data refuses to wrap one at all.
     */
    private function storeUnder(string $masterKeyId): DataKeyStore
    {
        return new DataKeyStore(
            $this->connection,
            $this->clients,
            $this->client,
            $masterKeyId,
            $this->secrets,
            $this->table,
            self::KEY_BYTES,
            $this->maxAgeSeconds,
            bindWrappingContext: true,
        );
    }
}
