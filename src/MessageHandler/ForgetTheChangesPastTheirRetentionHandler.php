<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\ForgetTheChangesPastTheirRetention;
use Beffroi\Core\Application\Identity\ClaimHistoryRetention;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetTheChangesPastTheirRetentionHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private ClaimHistoryRetention $retention,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ForgetTheChangesPastTheirRetention $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            // Nothing to do before the first provisioning, and a tick is not the place to say so.
            return;
        }
        $forgotten = $this->retention->enforce($tenant);
        if ($forgotten === 0) {
            return;
        }
        $this->logger->info('Forgot {count} change(s) of a record of the tenant "{tenant}", kept past their retention.', [
            'count' => $forgotten,
            'tenant' => $tenant->slug,
        ]);
    }
}
