<?php

declare(strict_types=1);

namespace App\Doctrine\PersonalData;

use function count;
use Doctrine\DBAL\Connection;
use App\Http\RequestContextFactory;
use Beffroi\Core\Domain\Event\TenantDataKeysDestroyed;
use Beffroi\Core\Domain\Tenant\Tenant;
use Psr\EventDispatcher\EventDispatcherInterface;
use function sprintf;

/**
 * A slug is lowercase letters, digits and hyphens, so the LIKE on the prefix of a scope reads no pattern in it.
 */
final readonly class TenantDataKeyDestruction
{
    public function __construct(
        private Connection $connection,
        private TenantDataKeys $keys,
        private EventDispatcherInterface $events,
        private RequestContextFactory $contexts,
        private string $table = TenantDataKeyStores::TABLE,
    ) {
    }

    public function destroy(Tenant $tenant, string $askedBy): int
    {
        $scopes = count($this->keys->of($tenant));
        $erased = (int) $this->connection->executeStatement(
            sprintf('DELETE FROM %s WHERE scope LIKE :prefix', $this->table),
            [
                'prefix' => $tenant->slug . TenantScopedEncrypter::SEPARATOR . '%',
            ],
        );

        $context = $this->contexts->fromConsole($askedBy);
        $this->events->dispatch(new TenantDataKeysDestroyed($tenant, $erased, $scopes, $context->receivedAt, $context));

        return $erased;
    }
}
