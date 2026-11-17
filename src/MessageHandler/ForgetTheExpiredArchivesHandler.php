<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\ForgetTheExpiredArchives;
use Beffroi\Core\Application\Archive\ArchiveRetention;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetTheExpiredArchivesHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private ArchiveRetention $retention,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ForgetTheExpiredArchives $message): void
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
        $this->logger->info('Forgot {count} expired archive(s) of the tenant "{tenant}".', [
            'count' => $forgotten,
            'tenant' => $tenant->slug,
        ]);
    }
}
