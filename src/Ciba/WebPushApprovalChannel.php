<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Locale\UserLocale;
use App\Push\PushMessage;
use App\Push\WebPushBroadcast;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;

/**
 * Sends no request identifier and no link that answers one: the notification only says something is waiting.
 */
final readonly class WebPushApprovalChannel implements PendingApprovalChannelInterface
{
    public function __construct(
        private WebPushBroadcast $broadcast,
        private PendingApprovalWording $wording,
        private UserLocale $locales,
    ) {
    }

    public function name(): string
    {
        return WebPushBroadcast::CHANNEL;
    }

    public function tell(User $user, PendingApproval $approval): bool
    {
        $locale = $this->locales->of($user);

        return $this->broadcast->toEveryBrowserOf($user, new PushMessage(
            $this->wording->title($approval, $locale),
            $this->wording->body($approval, $locale),
            $approval->approvalUri,
            $approval->expiresAt,
        ));
    }
}
