<?php

declare(strict_types=1);

namespace App\Http\ResponseMode;

use App\Http\InteractionPages;
use Beffroi\Oidc\Domain\Authorization\AuthorizationAnswer;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 6749 §4.1.2, OAuth 2.0 Multiple Response Type Encoding Practices §2.1
 */
final readonly class QueryResponseRenderer implements AuthorizationResponseRendererInterface
{
    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    public function mode(): string
    {
        return 'query';
    }

    public function render(AuthorizationAnswer $answer): Response
    {
        return $this->pages->redirect($answer->redirectUri->withParameters($answer->parameters));
    }
}
