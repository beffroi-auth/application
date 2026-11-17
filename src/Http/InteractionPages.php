<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The pages and redirections of the interaction plane, with the headers it needs: no framing (RFC 9700 §4.15), no
 * referrer, no caching.
 */
final readonly class InteractionPages
{
    public const string LOGIN_REQUIRED = 'login_required';

    public const string INVALID_CSRF_TOKEN = 'invalid_csrf_token';

    public const array HEADERS = [
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'no-store',
    ];

    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $headers
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK, array $headers = []): Response
    {
        return new Response($this->twig->render('@Beffroi/' . $template, $context), $status, $headers + self::HEADERS);
    }

    /**
     * The page shown when nothing may be sent back to a client (RFC 9700 §4.1, RP-Initiated Logout §4).
     *
     * @param string|null $description never an internal message
     * @param array<string, string> $headers
     */
    public function error(int $status, string $error, ?string $description = null, array $headers = []): Response
    {
        return $this->render(
            'error.html.twig',
            [
                'status' => $status,
                'error' => $error,
                'description' => $description,
            ],
            $status,
            $headers,
        );
    }

    /**
     * The file name names the thing, never the person: proxies and browsers write that header down.
     */
    public function download(string $content, string $mediaType, string $fileName): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $mediaType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName),
        ] + self::HEADERS);
    }

    /**
     * A 303 to the page of the form, so that a reload does not post it again (RFC 9110 §15.4.4).
     *
     * @param array<string, string> $parameters
     */
    public function afterTheForm(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->router->generate($route, $parameters), Response::HTTP_SEE_OTHER, self::HEADERS);
    }

    public function redirect(string $uri): RedirectResponse
    {
        return new RedirectResponse($uri, Response::HTTP_FOUND, self::HEADERS);
    }

    /**
     * The interaction is read from the query alone, never from the body nor from a referer.
     */
    public function redirectToPage(string $route, Request $request): RedirectResponse
    {
        $interaction = $request->query->getString(AuthorizationResponder::INTERACTION_PARAMETER);

        return $this->redirectToRoute(
            $route,
            $interaction === ''
                ? []
                : [
                    AuthorizationResponder::INTERACTION_PARAMETER => $interaction,
                ],
        );
    }

    /**
     * @param array<string, string> $parameters written by the provider, never what a request carried
     */
    public function redirectToRoute(string $route, array $parameters = []): RedirectResponse
    {
        return $this->redirect($this->router->generate($route, $parameters));
    }
}
