<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\SignTheJournal;
use Beffroi\Core\Application\Journal\JournalSignature;
use Beffroi\Core\Exception\BrokenJournalException;
use Beffroi\Core\Exception\ExceptionTrail;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Exception\KmsUnavailableException;
use Beffroi\Core\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SignTheJournalHandler
{
    public function __construct(
        private TenantProvider $tenants,
        private JournalSignature $signature,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SignTheJournal $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            // Nothing to do before the first provisioning, and a tick is not the place to say so.
            return;
        }
        try {
            $this->signature->sign($tenant);
        } catch (BrokenJournalException|KeyNotFoundException|KmsUnavailableException|RuntimeException $failure) {
            $this->logger->error('The journal of the tenant "{tenant}" was not signed: {reason}', [
                'tenant' => $tenant->slug,
                'reason' => ExceptionTrail::of($failure),
            ]);
        }
    }
}
