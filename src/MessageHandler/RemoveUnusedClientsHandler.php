<?php

declare(strict_types=1);

namespace App\MessageHandler;

use function count;
use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Http\RequestContextFactory;
use App\Message\RemoveUnusedClients;
use App\Scheduler\BeffroiSchedule;
use Beffroi\Oidc\Application\Client\Registration\UnusedClientRemoval;
use function implode;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveUnusedClientsHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private UnusedClientRemoval $removal,
        private RequestContextFactory $contexts,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemoveUnusedClients $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            return;
        }
        $removed = $this->removal->removeUnused($tenant, $this->contexts->fromSchedule(BeffroiSchedule::NAME));
        if ($removed === []) {
            return;
        }
        $this->logger->info('Removed {count} registered client(s) of the tenant "{tenant}" that nobody used any more: {clients}.', [
            'count' => count($removed),
            'tenant' => $tenant->slug,
            'clients' => implode(', ', $removed),
        ]);
    }
}
