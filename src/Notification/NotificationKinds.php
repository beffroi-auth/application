<?php

declare(strict_types=1);

namespace App\Notification;

use Beffroi\Core\Domain\Notification\ChoosableNotificationKindInterface;
use Beffroi\Core\Domain\Notification\NotificationKindInterface;

/**
 * The kinds of notification this provider tells a person of, in the order they are wired.
 */
final readonly class NotificationKinds
{
    public const string TAG = 'beffroi.notification_kind';

    /**
     * @param iterable<NotificationKindInterface> $kinds
     */
    public function __construct(
        private iterable $kinds,
    ) {
    }

    /**
     * @return list<ChoosableNotificationKindInterface>
     */
    public function choosable(): array
    {
        $choosable = [];
        foreach ($this->kinds as $kind) {
            if ($kind instanceof ChoosableNotificationKindInterface) {
                $choosable[] = $kind;
            }
        }

        return $choosable;
    }

    /**
     * @return list<NotificationKindInterface> the kinds no choice narrows, told by every channel that carries them
     */
    public function reachingEveryChannel(): array
    {
        $every = [];
        foreach ($this->kinds as $kind) {
            if (! $kind instanceof ChoosableNotificationKindInterface) {
                $every[] = $kind;
            }
        }

        return $every;
    }
}
