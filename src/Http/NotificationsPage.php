<?php

declare(strict_types=1);

namespace App\Http;

use App\Push\ApplicationServerKey;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Core\Exception\KeyNotFoundException;
use Beffroi\Core\Port\ApplicationServerAlgorithmsInterface;
use Beffroi\Core\Port\ApplicationServerKeyProviderInterface;
use Beffroi\Core\Port\CanPageActiveSubscriptionsInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class NotificationsPage
{
    public function __construct(
        private CanPageActiveSubscriptionsInterface $subscriptions,
        private ApplicationServerAlgorithmsInterface $algorithms,
        private ApplicationServerKeyProviderInterface $keys,
        private AskedPage $asked,
        private AskedPeriod $period,
        private InteractionPages $pages,
    ) {
    }

    public function render(User $user, ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        $period = $this->period->asked($user);

        return $this->pages->render(
            'account/notifications.html.twig',
            [
                'user' => $user,
                'subscriptions' => $this->subscriptions->pageOfActiveSubscriptions($user, $this->asked->asked(), $period),
                'period' => $period,
                'application_server_key' => $this->applicationServerKey($user)?->publicKey,
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
