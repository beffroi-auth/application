<?php

declare(strict_types=1);

namespace App\Http;

use App\Notification\NotificationKinds;
use App\Notification\OfferedChannels;
use App\Push\ApplicationServerKey;
use Beffroi\Core\Domain\Identity\PushSubscriptionQuestion;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Port\ApplicationServerAlgorithmsInterface;
use Beffroi\Core\Port\ApplicationServerKeyProviderInterface;
use Beffroi\Core\Port\CanPageActiveSubscriptionsInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class NotificationsPage
{
    public const string PUSHED_FROM = 'told_from';

    public const string PUSHED_TO = 'told_to';

    public function __construct(
        private CanPageActiveSubscriptionsInterface $subscriptions,
        private ApplicationServerAlgorithmsInterface $algorithms,
        private ApplicationServerKeyProviderInterface $keys,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
        private NotificationKinds $kinds,
        private OfferedChannels $channels,
    ) {
    }

    public function render(User $user, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $question = new PushSubscriptionQuestion(
            subscribed: $this->period->asked($user),
            pushed: $this->period->asked($user, self::PUSHED_FROM, self::PUSHED_TO),
        );

        return $this->pages->render(
            'account/notifications.html.twig',
            [
                'user' => $user,
                'subscriptions' => $this->subscriptions->pageOfActiveSubscriptions($user, $question, $this->asked->asked()),
                'question' => $question,
                'narrowed' => $question->subscribed !== null || $question->pushed !== null,
                'application_server_key' => $this->applicationServerKey($user)?->publicKey,
                'channels' => $this->channels->names(),
                'chosen_kinds' => $this->kinds->choosable(),
                'every_channel_kinds' => $this->kinds->reachingEveryChannel(),
                'chosen' => $user->notificationChannels,
                'error' => $error,
            ],
            $status,
        );
    }

    public function applicationServerKey(User $user): ?ApplicationServerKey
    {
        $algorithm = $this->algorithms->all()[0] ?? null;
        if ($algorithm === null) {
            return null;
        }
        try {
            return ApplicationServerKey::of($this->keys->activeApplicationServerKey($user->tenant, $algorithm));
        } catch (KeyNotFoundException) {
            return null;
        }
    }
}
