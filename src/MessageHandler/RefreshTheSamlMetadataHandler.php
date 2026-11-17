<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Doctrine\TenantNotSynchronizedException;
use App\Doctrine\TenantProvider;
use App\Message\RefreshTheSamlMetadata;
use Beffroi\Saml\Application\Metadata\MetadataRefresh;
use Beffroi\Saml\Domain\Metadata\PeerInterface;
use Exception;
use function array_keys;
use Psr\Log\LoggerInterface;
use function sprintf;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * beffroi/saml, spec/saml.md sections 6.2 and 6.10
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RefreshTheSamlMetadataHandler
{
    public const string TAG = 'beffroi.saml.metadata_refresh';

    /**
     * @param ServiceProviderInterface<MetadataRefresh<covariant PeerInterface>> $refreshes
     */
    public function __construct(
        private TenantProvider $tenants,
        private ServiceProviderInterface $refreshes,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshTheSamlMetadata $message): void
    {
        try {
            $tenant = $this->tenants->provide();
        } catch (TenantNotSynchronizedException) {
            return;
        }
        foreach (array_keys($this->refreshes->getProvidedServices()) as $role) {
            try {
                $refreshments = $this->refreshes->get($role)->refreshAll($tenant);
            } catch (Exception $refusal) {
                $this->logger->error(sprintf(
                    'The SAML metadata of the role "%s" could not be refreshed, the next role is read: %s was thrown: %s',
                    $role,
                    $refusal::class,
                    $refusal->getMessage(),
                ));
                continue;
            }
            foreach ($refreshments as $refreshment) {
                if ($refreshment->refused) {
                    $this->logger->error($refreshment->what);
                    continue;
                }
                $this->logger->info($refreshment->what);
            }
        }
    }
}
