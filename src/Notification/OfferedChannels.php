<?php

declare(strict_types=1);

namespace App\Notification;

use function in_array;

/**
 * The channels a person chooses among: those the deployment turns on, in the order they are wired.
 */
final readonly class OfferedChannels
{
    /**
     * @param iterable<ChannelInterface> $channels
     */
    public function __construct(
        private iterable $channels,
    ) {
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->channels as $channel) {
            if (! in_array($channel->name(), $names, true)) {
                $names[] = $channel->name();
            }
        }

        return $names;
    }
}
