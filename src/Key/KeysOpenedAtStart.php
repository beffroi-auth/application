<?php

declare(strict_types=1);

namespace App\Key;

use Beffroi\Core\Port\AmbientTenantInterface;
use Beffroi\Core\Port\OpenableKeysInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * What a process opens before it serves. A key that does not open is opened at its first use, as on a process started
 * while the KMS did not answer: a process that stopped there would stop serving what asks nothing of the KMS.
 */
final readonly class KeysOpenedAtStart
{
    public const string BY_A_THREAD = 'beffroi.keys.opened_by_a_thread';

    public const string BY_THE_CONSUMER = 'beffroi.keys.opened_by_the_consumer';

    /**
     * @param iterable<OpenableKeysInterface> $keys in the order they are opened
     */
    public function __construct(
        private AmbientTenantInterface $tenants,
        private iterable $keys,
        private LoggerInterface $logger,
    ) {
    }

    public function open(): void
    {
        try {
            $tenant = $this->tenants->provide();
            foreach ($this->keys as $keys) {
                $keys->open($tenant);
            }
        } catch (Exception $exception) {
            $outage = KmsUnavailableListener::outageBehind($exception);
            // An error and not a warning: the log of production holds back what comes below an error until one comes.
            $this->logger->error(
                $outage === null
                    ? 'The keys could not be opened at start: each one is opened at its first use.'
                    : 'The KMS did not answer while the keys were opened at start: each one is opened at its first use.',
                ['exception' => $outage ?? $exception],
            );
        }
    }
}
