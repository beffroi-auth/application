<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Locale\UserLocale;
use App\Sms\SmsSender;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;

/**
 * Texts no request identifier and no link that answers one: the text says which provider asks, who asks and on which
 * page the answer is given.
 */
final readonly class SmsApprovalChannel implements PendingApprovalChannelInterface
{
    public function __construct(
        private SmsSender $sender,
        private PendingApprovalWording $wording,
        private UserLocale $locales,
    ) {
    }

    public function name(): string
    {
        return SmsSender::CHANNEL;
    }

    public function tell(User $user, PendingApproval $approval): bool
    {
        $locale = $this->locales->of($user);

        return $this->sender->send(
            $user,
            $this->wording->title($approval, $locale) . "\n" . $this->wording->body($approval, $locale) . "\n" . $approval->approvalUri,
        );
    }
}
