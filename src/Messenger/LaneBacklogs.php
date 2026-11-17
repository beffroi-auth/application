<?php

declare(strict_types=1);

namespace App\Messenger;

use function str_starts_with;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Throwable;

/**
 * How many messages wait in each transport the worker consumes (architecture §5.6), as `messenger:stats` reads them:
 * by the transport itself, for one that can count its messages, the queues of the database among them. A transport
 * that cannot count, or that does not answer when it is asked, says nothing rather than zero.
 */
final readonly class LaneBacklogs
{
    /**
     * The prefix of the identifiers of the services under which the locator holds each transport a second time.
     */
    private const string SERVICE_PREFIX = 'messenger.transport.';

    /**
     * @param ServiceProviderInterface<object> $receivers the receivers of Messenger, by the name of their transport
     */
    public function __construct(
        private ServiceProviderInterface $receivers,
    ) {
    }

    /**
     * @return array<string, int|null> the messages waiting in each transport, by its name
     */
    public function backlogs(): array
    {
        $backlogs = [];
        foreach ($this->receivers->getProvidedServices() as $name => $type) {
            if (str_starts_with($name, self::SERVICE_PREFIX)) {
                continue;
            }
            $backlogs[$name] = $this->backlogOf($name);
        }

        return $backlogs;
    }

    private function backlogOf(string $name): ?int
    {
        try {
            $receiver = $this->receivers->get($name);

            return $receiver instanceof MessageCountAwareInterface ? $receiver->getMessageCount() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
