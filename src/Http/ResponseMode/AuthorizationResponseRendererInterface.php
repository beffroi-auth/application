<?php

declare(strict_types=1);

namespace App\Http\ResponseMode;

use Beffroi\Oidc\Domain\Authorization\AuthorizationAnswer;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth 2.0 Multiple Response Type Encoding Practices §2.1
 */
interface AuthorizationResponseRendererInterface
{
    /**
     * @return non-empty-string
     */
    public function mode(): string;

    public function render(AuthorizationAnswer $answer): Response;
}
