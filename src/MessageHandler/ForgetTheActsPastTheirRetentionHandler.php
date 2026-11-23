<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\ForgetTheActsPastTheirRetention;
use Beffroi\Core\Application\Journal\JournalCut;
use Beffroi\Core\Exception\BrokenJournalException;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use Beffroi\Oidc\Application\Disclosure\DisclosureRetention;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetTheActsPastTheirRetentionHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private JournalCut $cut,
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
        try {
            $forgotten = $this->cut->forgetPastTheWindow($tenant);
            if ($forgotten > 0) {
                $this->logger->info('Forgot {count} act(s) of the journal of the tenant "{tenant}", kept past their retention, and signed the cut.', [
                    'count' => $forgotten,
                    'tenant' => $tenant->slug,
                ]);
            }
        } catch (BrokenJournalException|KeyNotFoundException|KmsUnavailableException|RuntimeException $failure) {
            $this->logger->error('Forgot no act of the journal of the tenant "{tenant}": {reason}', [
                'tenant' => $tenant->slug,
                'reason' => ExceptionTrail::of($failure),
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
