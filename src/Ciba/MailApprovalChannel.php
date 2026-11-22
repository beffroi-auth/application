<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Locale\UserLocale;
use App\Mail\MailSender;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;

/**
 * Writes no request identifier and no link that answers one: the mail says who asks and on which page the answer is
 * given.
 */
final readonly class MailApprovalChannel implements PendingApprovalChannelInterface
{
    public function __construct(
        private MailSender $sender,
        private PendingApprovalWording $wording,
        private UserLocale $locales,
    ) {
    }

    public function tell(User $user, PendingApproval $approval): bool
    {
        $locale = $this->locales->of($user);

        return $this->sender->send(
            $user,
            $this->wording->subject($approval, $locale),
            $this->wording->body($approval, $locale) . "\n\n" . $approval->approvalUri . "\n",
        );
    }
}
