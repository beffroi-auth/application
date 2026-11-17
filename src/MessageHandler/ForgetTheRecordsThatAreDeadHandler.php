<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\ForgetTheRecordsThatAreDead;
use Beffroi\Core\Application\Record\DeadRecordRemoval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetTheRecordsThatAreDeadHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private DeadRecordRemoval $removal,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ForgetTheRecordsThatAreDead $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            // Nothing to do before the first provisioning, and a tick is not the place to say so.
            return;
        }
        $forgotten = $this->removal->removeDead($tenant);
        if ($forgotten === 0) {
            return;
        }
        $this->logger->info('Dropped {count} row(s) of the tenant "{tenant}" that could no longer do anything.', [
            'count' => $forgotten,
            'tenant' => $tenant->slug,
        ]);
    }
}
