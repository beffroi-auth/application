<?php

declare(strict_types=1);

namespace App\Http\ResponseMode;

use Beffroi\Oidc\Application\Authorization\ResponseDocumentIssuer;
use Beffroi\Oidc\Domain\Authorization\AuthorizationAnswer;
use Symfony\Component\HttpFoundation\Response;

/**
 * JARM §2.1, OAuth 2.0 Form Post Response Mode §2
 */
final readonly class SignedFormPostResponseRenderer implements SignsTheAnswerInterface
{
    public function __construct(
        private ResponseDocumentIssuer $documents,
        private FormPostResponseRenderer $delivery,
    ) {
    }

    public function mode(): string
    {
        return 'form_post.jwt';
    }

    public function render(AuthorizationAnswer $answer): Response
    {
        return $this->delivery->render($answer->signedWith($this->documents->issue($answer)));
    }
}
