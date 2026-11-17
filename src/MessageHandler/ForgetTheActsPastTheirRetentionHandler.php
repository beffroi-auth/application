<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\ForgetTheActsPastTheirRetention;
use Beffroi\Core\Application\Journal\JournalRetention;
use Beffroi\Oidc\Application\Disclosure\DisclosureRetention;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetTheActsPastTheirRetentionHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private JournalRetention $retention,
        private DisclosureRetention $disclosures,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ForgetTheActsPastTheirRetention $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            // Nothing to do before the first provisioning, and a tick is not the place to say so.
            return;
        }
        $forgotten = $this->retention->enforce($tenant);
        if ($forgotten > 0) {
            $this->logger->info('Forgot {count} act(s) of the journal of the tenant "{tenant}", kept past their retention.', [
                'count' => $forgotten,
                'tenant' => $tenant->slug,
            ]);
        }
        $given = $this->disclosures->enforce($tenant);
        if ($given === 0) {
            return;
        }
        $this->logger->info('Forgot what {count} act(s) of the tenant "{tenant}" had given to a client, kept past the retention of the journal.', [
            'count' => $given,
            'tenant' => $tenant->slug,
        ]);
    }
}
