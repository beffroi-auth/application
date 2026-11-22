<?php

declare(strict_types=1);

namespace App\Credential;

use function sprintf;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The channels a change is told through, each found again by its name.
 */
final readonly class CredentialChangeChannels
{
    /**
     * @var list<string>
     */
    private array $names;

    /**
     * @var array<string, CredentialChangeChannelInterface>
     */
    private array $channels;

    /**
     * @param iterable<CredentialChangeChannelInterface> $channels in the order a change is told through them
     *
     * @throws InvalidConfigurationException when two channels go by the same name
     */
    public function __construct(iterable $channels)
    {
        $names = [];
        $named = [];
        foreach ($channels as $channel) {
            $name = $channel->name();
            if (isset($named[$name])) {
                throw new InvalidConfigurationException(sprintf(
                    'Two channels that tell a person of a change to their credentials go by the name "%s": a notice for that name would reach the first one alone.',
                    $name,
                ));
            }
            $names[] = $name;
            $named[$name] = $channel;
        }
        $this->names = $names;
        $this->channels = $named;
    }

    /**
     * @return list<string> in the order a change is told through them
     */
    public function names(): array
    {
        return $this->names;
    }

    public function named(string $name): ?CredentialChangeChannelInterface
    {
        return $this->channels[$name] ?? null;
    }
}
