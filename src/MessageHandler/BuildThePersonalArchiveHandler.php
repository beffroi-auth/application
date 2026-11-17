<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Beffroi\Core\Application\Archive\ArchiveAssembly;
use Beffroi\Core\Domain\Event\PersonAskedForTheirArchive;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class BuildThePersonalArchiveHandler
{
    public function __construct(
        private ArchiveAssembly $assembly,
    ) {
    }

    public function __invoke(PersonAskedForTheirArchive $event): void
    {
        $this->assembly->assemble($event->tenant, $event->archiveId);
    }
}
