<?php

declare(strict_types=1);

namespace App\Http\ResponseMode;

use Beffroi\Oidc\Application\Authorization\ResponseDocumentIssuer;
use Beffroi\Oidc\Domain\Authorization\AuthorizationAnswer;
use Symfony\Component\HttpFoundation\Response;

/**
 * JARM §2.1
 */
final readonly class SignedQueryResponseRenderer implements AnswersTheJwtAliasInterface
{
    public function __construct(
        private ResponseDocumentIssuer $documents,
        private QueryResponseRenderer $delivery,
    ) {
    }

    public function mode(): string
    {
        return 'query.jwt';
    }

    public function render(AuthorizationAnswer $answer): Response
    {
        return $this->delivery->render($answer->signedWith($this->documents->issue($answer)));
    }
}
