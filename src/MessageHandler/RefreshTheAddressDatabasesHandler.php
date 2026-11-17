<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Geo\GeoLite2Refresh;
use App\Message\RefreshTheAddressDatabases;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RefreshTheAddressDatabasesHandler
{
    public function __construct(
        private GeoLite2Refresh $refresh,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshTheAddressDatabases $message): void
    {
        foreach ($this->refresh->refresh() as $refreshment) {
            if ($refreshment->refused) {
                $this->logger->warning($refreshment->what);
                continue;
            }
            $this->logger->info($refreshment->what);
        }
    }
}
