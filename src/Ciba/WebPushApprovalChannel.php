<?php

declare(strict_types=1);

namespace App\Ciba;

use App\Locale\UserLocale;
use App\Push\PushMessage;
use App\Push\WebPushBroadcast;
use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\PendingApproval;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends no request identifier and no link that answers one: the notification only says something is waiting.
 */
final readonly class WebPushApprovalChannel implements PendingApprovalChannelInterface
{
    public const string TRANSLATION_DOMAIN = 'beffroi';

    public function __construct(
        private WebPushBroadcast $broadcast,
        private TranslatorInterface $translator,
        private UserLocale $locales,
    ) {
    }

    public function tell(User $user, PendingApproval $approval): void
    {
        $this->broadcast->toEveryBrowserOf($user, $this->message($approval, $this->locales->of($user)));
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
}
