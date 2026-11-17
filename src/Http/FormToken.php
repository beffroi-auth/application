<?php

declare(strict_types=1);

namespace App\Http;

use function is_string;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class FormToken
{
    public const string PARAMETER = '_token';

    public function __construct(
        private CsrfTokenManagerInterface $csrf,
        private InteractionPages $pages,
    ) {
    }

    public function holds(mixed $token, string $csrfTokenId): bool
    {
        return is_string($token) && $token !== '' && $this->csrf->isTokenValid(new CsrfToken($csrfTokenId, $token));
    }

    public function refusal(mixed $token, string $csrfTokenId): ?Response
    {
        return $this->holds($token, $csrfTokenId)
            ? null
            : $this->pages->error(Response::HTTP_FORBIDDEN, InteractionPages::INVALID_CSRF_TOKEN);
    }
}
