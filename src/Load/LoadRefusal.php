<?php

declare(strict_types=1);

namespace App\Load;

use App\Http\InteractionLocale;
use App\Http\InteractionPages;
use App\Http\ProtocolAnswer;
use App\Plane\Plane;
use Beffroi\Oidc\Domain\Token\TokenErrorResponse;
use Beffroi\Oidc\Domain\Token\TokenResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The 429 of RFC 6585 §4. Its body on the protocol plane carries no "error": no value registered by RFC 6749 §11.4
 * says a budget is spent, and "slow_down" means a request still pending (RFC 8628 §3.5).
 */
final readonly class LoadRefusal
{
    public const int STATUS = Response::HTTP_TOO_MANY_REQUESTS;

    public const string PAGE_ERROR = 'too_many_requests';

    public const string DESCRIPTION = 'Too many requests were sent to this provider; this one was not served. Try again after the delay the Retry-After header gives.';

    public function __construct(
        private InteractionPages $pages,
        private InteractionLocale $locales,
    ) {
    }

    /**
     * @param int $retryAfter seconds
     */
    public function response(?Plane $plane, int $retryAfter, Request $request): Response
    {
        if ($plane !== Plane::Interaction) {
            return ProtocolAnswer::json(
                ['error_description' => self::DESCRIPTION],
                self::STATUS,
                TokenErrorResponse::CONTENT_TYPE,
                self::retryAfter($retryAfter) + TokenResponse::HEADERS,
            );
        }
        // The budget is consumed before the listener that chooses the language of a page, so it is chosen here.
        $this->locales->apply($request, $this->locales->choose($request));

        return $this->pages->error(self::STATUS, self::PAGE_ERROR, headers: self::retryAfter($retryAfter));
    }

    /**
     * @return array<string, string>
     */
    private static function retryAfter(int $retryAfter): array
    {
        return [
            'Retry-After' => (string) $retryAfter,
        ];
    }
}
