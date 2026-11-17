<?php

declare(strict_types=1);

namespace App\Saml;

use function array_map;
use function array_unique;
use App\Http\InteractionPages;
use App\Http\SourceOrigin;
use Beffroi\Oidc\Domain\Page\InlineScript;
use Beffroi\Saml\Domain\SingleSignOn\PostedForm;
use function implode;
use LogicException;
use function parse_url;
use const PHP_URL_HOST;
use const PHP_URL_PORT;
use const PHP_URL_SCHEME;
use function sprintf;
use Symfony\Component\HttpFoundation\Response;

/**
 * A response of single sign-on and an AuthnRequest name no "form-action" (beffroi/saml S-36, S-51): the endpoint
 * they post to redirects to an origin nobody states, and a browser checks the directive over every redirection.
 */
final readonly class SamlPostPage
{
    public const string TEMPLATE = 'saml_post.html.twig';

    public const string SCRIPT = 'document.forms[0].submit();';

    public function __construct(
        private InteractionPages $pages,
    ) {
    }

    public function signOnResponse(PostedForm $form): Response
    {
        return $this->render($form, self::policyWithoutFormAction());
    }

    public function signOnRequest(PostedForm $form): Response
    {
        return $this->render($form, self::policyWithoutFormAction(), upstream: true);
    }

    public function logoutMessage(PostedForm $form): Response
    {
        return $this->render($form, sprintf(
            "default-src 'none'; script-src '%s'; form-action %s; base-uri 'none'; frame-ancestors 'none'",
            new InlineScript(self::SCRIPT)->cspHashSource(),
            implode(' ', array_unique(array_map(self::originOf(...), [$form->action, ...$form->reaches]))),
        ));
    }

    private function render(PostedForm $form, string $policy, bool $upstream = false): Response
    {
        return $this->pages->render(
            self::TEMPLATE,
            [
                'action' => $form->action,
                'fields' => $form->fields,
                'script' => self::SCRIPT,
                'upstream' => $upstream,
            ],
            headers: [
                'Content-Security-Policy' => $policy,
                'Cache-Control' => 'no-cache, no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }

    private static function policyWithoutFormAction(): string
    {
        return sprintf(
            "default-src 'none'; script-src '%s'; base-uri 'none'; frame-ancestors 'none'",
            new InlineScript(self::SCRIPT)->cspHashSource(),
        );
    }

    /**
     * The origin a "form-action" names (CSP Level 3 §2.3.1), with nothing that would split the policy it is
     * written into.
     *
     * @throws LogicException
     */
    public static function originOf(string $url): string
    {
        $port = parse_url($url, PHP_URL_PORT);

        return SourceOrigin::of(sprintf(
            '%s://%s%s',
            (string) parse_url($url, PHP_URL_SCHEME),
            (string) parse_url($url, PHP_URL_HOST),
            $port === null || $port === false ? '' : ':' . $port,
        ));
    }
}
