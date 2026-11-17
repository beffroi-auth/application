<?php

declare(strict_types=1);

namespace App\Doctrine\Repository;

use Doctrine\DBAL\Types\Types;
use SortDirection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use App\Doctrine\EntityManagers;
use Beffroi\Core\Domain\Tenant\Tenant;
use Beffroi\Oidc\Application\Client\ClientMetadataReader;
use Beffroi\Oidc\Domain\Client\Client;
use Beffroi\Oidc\Domain\Client\ClientRecord;
use Beffroi\Oidc\Domain\Client\ClientRegistration;
use Beffroi\Oidc\Domain\Client\InventoriedClient;
use Beffroi\Oidc\Domain\Client\Registration\ClientRetention;
use Beffroi\Oidc\Port\ClientInventoryInterface;
use Beffroi\Oidc\Port\ClientSourceInterface;
use Beffroi\Oidc\Port\ClientStoreInterface;
use Beffroi\Oidc\Exception\UnreadableClientMetadataException;
use Beffroi\Oidc\Port\UnusedClientsInterface;
use function is_string;
use Beffroi\Oidc\Port\AdministrationResourceInterface;
use Psr\Log\LoggerInterface;
use function sprintf;

/**
 * @extends DoctrineRepository<ClientRecord>
 */
final readonly class DoctrineClientRepository extends DoctrineRepository implements
    ClientInventoryInterface,
    ClientSourceInterface,
    ClientStoreInterface,
    UnusedClientsInterface
{
    public const string SOURCE = 'registration';

    public function __construct(
        EntityManagers $managers,
        private ClientMetadataReader $reader,
        private LoggerInterface $logger,
        private AdministrationResourceInterface $administration,
    ) {
        parent::__construct($managers, ClientRecord::class);
    }

    public function findByClientId(Tenant $tenant, string $clientId): ?Client
    {
        $record = $this->record($tenant, $clientId);
        $registration = $record === null ? null : $this->registrationOf($record);

        return $record === null || $registration === null ? null : new Client($tenant, $registration, $record->secret);
    }

    public function registrationsIn(Tenant $tenant): iterable
    {
        foreach ($this->recordsIn($tenant) as $record) {
            $registration = $this->registrationOf($record);
            if ($registration !== null) {
                yield $registration;
            }
        }
    }

    public function inventoryIn(Tenant $tenant): iterable
    {
        foreach ($this->recordsIn($tenant) as $record) {
            yield new InventoriedClient(
                self::SOURCE,
                $record->clientId,
                self::registeredName($record),
                $record->issuedAt,
                $record->lastUsedAt,
            );
        }
    }

    public function save(ClientRecord $record): void
    {
        $this->store($record);
    }

    public function findRecord(Tenant $tenant, string $clientId): ?ClientRecord
    {
        return $this->record($tenant, $clientId);
    }

    public function unusedIn(Tenant $tenant, ClientRetention $retention, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        /** @var list<ClientRecord> $unused */
        $unused = $this->queryBuilder('c')
            ->andWhere('c.tenant = :tenant')
            ->andWhere('(c.lastUsedAt IS NOT NULL AND c.lastUsedAt < :usedSince) OR (c.lastUsedAt IS NULL AND c.issuedAt < :registeredSince)')
            ->setParameter('tenant', $tenant->id, UuidType::NAME)
            ->setParameter('usedSince', $retention->usedSince, Types::DATETIME_IMMUTABLE)
            ->setParameter('registeredSince', $retention->registeredSince, Types::DATETIME_IMMUTABLE)
            ->addOrderBy('c.lastUsedAt', SortDirection::Ascending)
            ->addOrderBy('c.issuedAt', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $unused;
    }

    public function remove(ClientRecord $record): void
    {
        $this->discard($record);
    }

    private static function registeredName(ClientRecord $record): ?string
    {
        $name = $record->metadata['client_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function record(Tenant $tenant, string $clientId): ?ClientRecord
    {
        return $this->repository()->findOneBy([
            'tenant' => $tenant,
            'clientId' => $clientId,
        ]);
    }

    /**
     * @return list<ClientRecord>
     */
    private function recordsIn(Tenant $tenant): array
    {
        return $this->repository()->findBy([
            'tenant' => $tenant,
        ], [
            'issuedAt' => 'ASC',
        ]);
    }

    private function registrationOf(ClientRecord $record): ?ClientRegistration
    {
        try {
            return $this->reader->read(
                $record->clientId,
                $record->asRegistered($this->administration->administrationResourceOf($record->tenant)),
                $record->secret !== null,
            );
        } catch (UnreadableClientMetadataException $refusal) {
            $this->logger->error(sprintf(
                'The client "%s" registered metadata this provider refuses now, and is served as no client: %s',
                $record->clientId,
                $refusal->getMessage(),
            ));

            return null;
        }
    }
}
