<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Beffroi\Saml\Application\SingleLogout\SessionParticipants;
use Beffroi\Saml\Domain\Event\ServiceProviderSignedIn;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * beffroi/saml S-26
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class RecordTheServiceProviderOfTheSessionHandler
{
    public function __construct(
        private SessionParticipants $participants,
    ) {
    }

    public function __invoke(ServiceProviderSignedIn $event): void
    {
        $this->participants->signedIn($event);
    }
}
