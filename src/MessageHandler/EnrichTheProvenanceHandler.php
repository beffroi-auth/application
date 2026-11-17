<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Beffroi\Core\Application\Session\SessionProvenanceEnrichment;
use Beffroi\Core\Domain\Event\SessionProvenanceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class EnrichTheProvenanceHandler
{
    public function __construct(
        private SessionProvenanceEnrichment $enrichment,
    ) {
    }

    public function __invoke(SessionProvenanceInterface $event): void
    {
        $this->enrichment->enrich($event->sessionId());
    }
}
