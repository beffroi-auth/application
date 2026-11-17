<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Device\DeviceAuthorizationRecord;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 8628 §3.3, BCP 10027 §2
 */
final readonly class DeviceApprovalPage
{
    /**
     * Unknown, expired, answered or spent alike, so that a code tried at random teaches nothing (RFC 8628 §5.1).
     */
    public const string UNKNOWN_CODE = 'unknown_code';

    public const string TOO_MANY_ATTEMPTS = 'too_many_attempts';

    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    public function renderForm(?string $refusal = null, ?string $shown = null, int $status = Response::HTTP_OK): Response
    {
        return $this->pages->render(
            'device.html.twig',
            [
                'refusal' => $refusal,
                'typed' => $shown,
            ],
            $status,
        );
    }

    public function renderApproval(DeviceAuthorizationRecord $record, User $user, string $shown): Response
    {
        return $this->pages->render('device_approval.html.twig', [
            'record' => $record,
            'client' => $record->client,
            'user' => $user,
            'scopes' => $record->scopes,
            'requested_from' => $record->requestedFrom,
            'typed' => $shown,
        ]);
    }

    public function renderAnswer(DeviceAuthorizationRecord $record): Response
    {
        return $this->pages->render('device_answered.html.twig', [
            'record' => $record,
            'client' => $record->client,
            'approved' => $record->isApproved(),
        ]);
    }
}
