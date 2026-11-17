<?php

declare(strict_types=1);

namespace App\Http;

use Beffroi\Core\Domain\Identity\User;
use Beffroi\Oidc\Domain\Ciba\BackchannelAuthenticationRecord;
use Symfony\Component\HttpFoundation\Response;

final readonly class PendingApprovalsPage
{
    public const string NOTHING_TO_ANSWER = 'nothing_to_answer';

    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    /**
     * @param list<BackchannelAuthenticationRecord> $records oldest first
     */
    public function renderList(
        User $user,
        array $records,
        ?string $refusal = null,
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->pages->render(
            'approvals.html.twig',
            [
                'user' => $user,
                'records' => $records,
                'refusal' => $refusal,
            ],
            $status,
        );
    }

    public function renderAnswer(BackchannelAuthenticationRecord $record): Response
    {
        return $this->pages->render('approval_answered.html.twig', [
            'record' => $record,
            'client' => $record->client,
            'approved' => $record->isApproved(),
        ]);
    }
}
