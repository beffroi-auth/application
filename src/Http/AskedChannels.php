<?php

declare(strict_types=1);

namespace App\Http;

use App\Notification\NotificationKinds;
use App\Notification\OfferedChannels;
use Beffroi\Core\Domain\Notification\ChosenChannels;
use Beffroi\Core\Exception\InvalidArgumentException;
use function in_array;
use function is_array;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the form of the notifications page chose: the channels of every kind, and those of each kind set apart. Only the
 * channels offered and the kinds a person chooses for are read, whatever else the body names.
 */
final readonly class AskedChannels
{
    public const string EVERY_KIND = 'every';

    public const string SET_APART = 'apart';

    public const string OF_A_KIND = 'channels';

    public function __construct(
        private NotificationKinds $kinds,
        private OfferedChannels $channels,
    ) {
    }

    /**
     * @throws BadRequestException      when a member of the form is not the list it names
     * @throws InvalidArgumentException when a channel the deployment wires is not a name
     */
    public function asked(Request $request): ChosenChannels
    {
        $offered = $this->channels->names();
        $body = $request->request;
        $apart = $body->all(self::SET_APART);
        $ofAKind = $body->all(self::OF_A_KIND);
        $setApart = [];
        foreach ($this->kinds->choosable() as $kind) {
            if (in_array($kind->name(), $apart, true)) {
                $chosen = $ofAKind[$kind->name()] ?? [];
                $setApart[$kind->name()] = self::among(is_array($chosen) ? $chosen : [], $offered);
            }
        }

        return new ChosenChannels(self::among($body->all(self::EVERY_KIND), $offered), $setApart);
    }

    /**
     * @param array<array-key, mixed> $said
     * @param list<string>            $offered
     *
     * @return list<string>
     */
    private static function among(array $said, array $offered): array
    {
        $among = [];
        foreach ($offered as $channel) {
            if (in_array($channel, $said, true)) {
                $among[] = $channel;
            }
        }

        return $among;
    }
}
