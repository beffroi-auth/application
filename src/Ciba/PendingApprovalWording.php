<?php

declare(strict_types=1);

namespace App\Ciba;

use Beffroi\Oidc\Domain\Ciba\PendingApproval;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What every channel tells a person of a pending approval: who asks and what they wrote, never what names the request.
 */
final readonly class PendingApprovalWording
{
    private const string TRANSLATION_DOMAIN = 'beffroi';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function title(PendingApproval $approval, string $locale): string
    {
        return $this->translator->trans(
            'approval.notice.title',
            [
                'provider' => $approval->tenant->name,
            ],
            self::TRANSLATION_DOMAIN,
            $locale,
        );
    }

    public function subject(PendingApproval $approval, string $locale): string
    {
        return $this->translator->trans(
            'approval.notice.subject',
            [
                'provider' => $approval->tenant->name,
            ],
            self::TRANSLATION_DOMAIN,
            $locale,
        );
    }

    public function body(PendingApproval $approval, string $locale): string
    {
        $binding = $approval->bindingMessage;

        return (
            $binding === null
                ? $this->translator->trans(
                    'approval.notice.body',
                    [
                        'client' => $approval->clientName,
                    ],
                    self::TRANSLATION_DOMAIN,
                    $locale,
                )
                : $this->translator->trans(
                    'approval.notice.body_with_message',
                    [
                        'client' => $approval->clientName,
                        'message' => $binding->value,
                    ],
                    self::TRANSLATION_DOMAIN,
                    $locale,
                )
        );
    }
}
