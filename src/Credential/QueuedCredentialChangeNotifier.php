<?php

declare(strict_types=1);

namespace App\Credential;

use App\Message\TellUserOfCredentialChange;
use Beffroi\Core\Domain\Credential\CredentialChange;
use Beffroi\Core\Port\CredentialChangeNotifierInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class QueuedCredentialChangeNotifier implements CredentialChangeNotifierInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private CredentialChangeChannels $channels,
    ) {
    }

    /**
     * NIST SP 800-63B-4 §4.6
     *
     * A notice per channel: the lane brings a failed message back whole, so a channel that failed is tried again on
     * its own and one that reached the person is not.
     */
    public function tell(CredentialChange $change): void
    {
        foreach ($this->channels->names() as $channel) {
            $this->commandBus->dispatch(new TellUserOfCredentialChange($change, $channel));
        }
    }
}
