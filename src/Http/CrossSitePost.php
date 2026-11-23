<?php

declare(strict_types=1);

namespace App\Http;

use function array_pad;
use Beffroi\Oidc\Application\Request\RequestParameters as OidcRequestParameters;
use Beffroi\Oidc\Domain\Page\InlineScript;
use Beffroi\Oidc\Exception\InvalidRequestException;
use function explode;
use function preg_match;
use function sprintf;
use function str_contains;
use function strtolower;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function trim;
use function urldecode;

/**
 * Posts a form a browser sent from another site again from this provider's own origin, so that the session
 * cookie set "SameSite=Lax" accompanies it (beffroi/saml S-28).
 *
 * The marker decides nothing: the second post is same-site, which is what stops the loop. Nothing here starts the
 * HTTP session, whose cookie on this answer would replace the one the browser holds.
 */
final readonly class CrossSitePost
{
    public const string TEMPLATE = 'post_again.html.twig';

    public const string SCRIPT = 'document.forms[0].submit();';

    public const string MARKER = '_beffroi_posted_again';

    private const string FORM = 'application/x-www-form-urlencoded';

    /**
     * What a browser does not post back from a hidden field as it was given: a control character, and a CR or an LF
     * outside a CR LF pair (HTML, "converting an entry list to a list of name-value pairs").
     */
    private const string REWRITTEN = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\r(?!\n)|(?<!\r)\n/';

    /**
     * Posted by a browser with the name of its encoding as the value (HTML, "constructing the entry list").
     */
    private const string CHARSET = '_charset_';

    public function __construct(
        private InteractionPages $pages,
        private InteractionLocale $locale,
    ) {
    }

    public static function isAForm(Request $request): bool
    {
        return strtolower(trim(explode(';', $request->headers->get('Content-Type') ?? '')[0])) === self::FORM;
    }

    public function postAgain(Request $request): ?Response
    {
        return self::isPostedAgain($request) ? $this->page($request, []) : null;
    }

    /**
     * Nothing else of the request is resolved: reading the reference of a pushed request spends it.
     */
    public function postAgainWithItsUiLocales(Request $request): ?Response
    {
        if (! self::isPostedAgain($request)) {
            return null;
        }
        try {
            $uiLocales = OidcRequestParameters::of(RequestParameters::ofForm($request))->uiLocales();
        } catch (InvalidRequestException) {
            $uiLocales = [];
        }

        return $this->page($request, $uiLocales);
    }

    private static function isPostedAgain(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_POST) && self::isAForm($request) && CrossSiteSessionCookie::withheldFrom($request);
    }

    /**
     * @param list<string> $uiLocales most preferred first
     */
    private function page(Request $request, array $uiLocales): ?Response
    {
        $fields = self::fields($request->getContent());
        if ($fields === null) {
            return null;
        }
        $action = $request->getRequestUri();
        if (! $request->query->has(self::MARKER)) {
            $action .= (str_contains($action, '?') ? '&' : '?') . self::MARKER . '=1';
        }
        $this->locale->apply($request, $this->locale->choose($request, $uiLocales));

        return $this->pages->render(
            self::TEMPLATE,
            [
                'action' => $action,
                'fields' => $fields,
                'script' => self::SCRIPT,
            ],
            headers: [
                // No "form-action": a browser checks it over every redirection, which may end at a client.
                'Content-Security-Policy' => sprintf(
                    "default-src 'none'; script-src '%s'; base-uri 'none'; frame-ancestors 'none'",
                    new InlineScript(self::SCRIPT)->cspHashSource(),
                ),
                'Cache-Control' => 'no-cache, no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }

    /**
     * @return non-empty-list<array{string, string}>|null
     */
    private static function fields(string $body): ?array
    {
        $fields = [];
        foreach (explode('&', $body) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);
            $value = urldecode($value);
            if ($name === '' || $name === self::CHARSET || ! self::returns($name) || ! self::returns($value)) {
                return null;
            }
            $fields[] = [$name, $value];
        }

        return $fields === [] ? null : $fields;
    }

    private static function returns(string $text): bool
    {
        return preg_match('//u', $text) === 1 && preg_match(self::REWRITTEN, $text) === 0;
    }
}
