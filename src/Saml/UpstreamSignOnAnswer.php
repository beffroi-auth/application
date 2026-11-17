<?php

declare(strict_types=1);

namespace App\Saml;

use App\Http\InteractionPages;
use Beffroi\Saml\Domain\SingleLogout\RedirectedMessage;
use Beffroi\Saml\Domain\Upstream\SentAuthnRequest;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * beffroi/saml S-51, SAML Bindings §3.4.5.1, §3.5.5.1
 */
final readonly class UpstreamSignOnAnswer
{
    public function __construct(
        private SamlPostPage $page,
    ) {
    }

    public function of(SentAuthnRequest $sent): Response
    {
        if ($sent->message instanceof RedirectedMessage) {
            return new RedirectResponse($sent->message->url, Response::HTTP_FOUND, [
                ...InteractionPages::HEADERS,
                'Cache-Control' => 'no-cache, no-store',
                'Pragma' => 'no-cache',
            ]);
        }

        return $this->page->signOnRequest($sent->message);
    }
}
