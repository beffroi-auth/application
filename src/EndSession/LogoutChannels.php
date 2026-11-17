<?php

declare(strict_types=1);

namespace App\EndSession;

use function array_values;
use Beffroi\Oidc\Domain\EndSession\LogoutChannel;
use Beffroi\Oidc\Port\ServedLogoutChannelsInterface;

final readonly class LogoutChannels implements ServedLogoutChannelsInterface
{
    /**
     * @var array<string, LogoutChannel>
     */
    private array $channels;

    /**
     * @param iterable<LogoutChannelInterface> $channels
     */
    public function __construct(iterable $channels)
    {
        $byName = [];
        foreach ($channels as $channel) {
            $byName[$channel->channel()->value] = $channel->channel();
        }
        $this->channels = $byName;
    }

    public function servedLogoutChannels(): array
    {
        return array_values($this->channels);
    }
}
