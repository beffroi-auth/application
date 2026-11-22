<?php

declare(strict_types=1);

namespace App\Push;

use App\Messenger\NotificationNotDeliveredException;
use Beffroi\Core\Application\Push\PushSubscriptionRevocation;
use Beffroi\Core\Domain\Identity\PushSubscription;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;
use function implode;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use function sprintf;

/**
 * RFC 8030 §7.3
 *
 * One browser reached is enough; one its push service says is gone is taken out, never tried again.
 */
final readonly class WebPushBroadcast
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSubscriptionRevocation $revocation,
        private WebPushSender $sender,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool false when the person has no browser left to push to, and was told nothing
     *
     * @throws NotificationNotDeliveredException when no browser was reached and a later attempt may reach one
     */
    public function toEveryBrowserOf(User $user, PushMessage $message): bool
    {
        $reached = false;
        $failures = [];
        foreach ($this->subscriptions->findActiveByUser($user) as $subscription) {
            $delivery = $this->sender->send($subscription, $message);
            $reached = $reached || $delivery->delivered;
            if ($delivery->delivered) {
                $subscription->markPushed($this->clock->now());
                $this->subscriptions->save($subscription);

                continue;
            }
            if ($delivery->gone) {
                $this->revocation->revoke($subscription);
                $this->logger->info('A push subscription is gone and has been taken out.', self::trail($subscription, $delivery->detail));

                continue;
            }
            $failures[] = $delivery->detail;
            $this->logger->warning('A push notification was not taken.', self::trail($subscription, $delivery->detail));
        }
        if (! $reached && $failures !== []) {
            throw new NotificationNotDeliveredException(sprintf(
                'No browser of this user was reached: %s.',
                implode('; ', $failures),
            ));
        }

        return $reached;
    }

    /**
     * Never the endpoint: it names the browser of a person at their push service.
     *
     * @return array<string, string>
     */
    private static function trail(PushSubscription $subscription, string $detail): array
    {
        return [
            'subscription' => $subscription->id->toRfc4122(),
            'tenant' => $subscription->tenant->slug,
            'detail' => $detail,
        ];
    }
}
