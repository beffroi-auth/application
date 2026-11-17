<?php

declare(strict_types=1);

namespace App\Http\ResponseMode;

use App\Http\InteractionPages;
use Beffroi\Oidc\Domain\Authorization\AuthorizationAnswer;
use Beffroi\Oidc\Domain\Authorization\FormPostResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth 2.0 Form Post Response Mode §2
 */
final readonly class FormPostResponseRenderer implements AuthorizationResponseRendererInterface
{
    public const string TEMPLATE = 'form_post.html.twig';

    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    public function mode(): string
    {
        return 'form_post';
    }

    public function render(AuthorizationAnswer $answer): Response
    {
        $response = new FormPostResponse($answer->redirectUri, $answer->parameters);

        return $this->pages->render(
            self::TEMPLATE,
            [
                'redirect_uri' => $response->redirectUri->value,
                'parameters' => $response->parameters,
                'script' => FormPostResponse::SCRIPT,
            ],
            headers: [
                'Content-Security-Policy' => $response->contentSecurityPolicy(),
            ],
        );
    }
}
