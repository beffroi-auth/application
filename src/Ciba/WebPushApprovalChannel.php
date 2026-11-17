<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Locale\UserLocale;
use App\Messenger\NotificationNotDeliveredException;
use App\Push\PushMessage;
use App\Push\WebPushSender;
use Beffroi\Core\Application\Push\PushSubscriptionRevocation;
use Beffroi\Core\Domain\Identity\PushSubscription;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Port\PushSubscriptionRepositoryInterface;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;
use function implode;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use function sprintf;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends no request identifier and no link that answers one: the notification only says something is waiting.
 */
final readonly class WebPushApprovalChannel implements PendingApprovalChannelInterface
{
    public const string TRANSLATION_DOMAIN = 'beffroi';

    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSubscriptionRevocation $revocation,
        private WebPushSender $sender,
        private TranslatorInterface $translator,
        private UserLocale $locales,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function tell(User $user, PendingApproval $approval): void
    {
        $subscriptions = $this->subscriptions->findActiveByUser($user);
        if ($subscriptions === []) {
            return;
        }
        $message = $this->message($approval, $this->locales->of($user));
        $reached = false;
        $failures = [];
        foreach ($subscriptions as $subscription) {
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
    }

    private function message(PendingApproval $approval, string $locale): PushMessage
    {
        $binding = $approval->bindingMessage;

        return new PushMessage(
            $this->translator->trans(
                'push.approval.title',
                [
                    'provider' => $approval->tenant->name,
                ],
                self::TRANSLATION_DOMAIN,
                $locale,
            ),
            $binding === null
                ? $this->translator->trans(
                    'push.approval.body',
                    [
                        'client' => $approval->clientName,
                    ],
                    self::TRANSLATION_DOMAIN,
                    $locale,
                )
                : $this->translator->trans(
                    'push.approval.body_with_message',
                    [
                        'client' => $approval->clientName,
                        'message' => $binding->value,
                    ],
                    self::TRANSLATION_DOMAIN,
                    $locale,
                ),
            $approval->approvalUri,
            $approval->expiresAt,
        );
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
