<?php

declare(strict_types=1);

namespace App\Theme;

use function preg_match;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ChooseThemeController
{
    public const string ROUTE = 'beffroi_interaction_theme';

    public function __construct(
        private ChosenTheme $chosen,
    ) {
    }

    #[Route('/theme', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $response = new RedirectResponse(self::backTo($request));
        $response->headers->setCookie($this->chosen->cookie($request, Theme::ofChoice($request->request->getString('theme'))));

        return $response;
    }

    /**
     * A path of this origin or the root: "//" and "/\" would send the browser to another host.
     */
    private static function backTo(Request $request): string
    {
        $target = $request->request->getString('target');

        return preg_match('#^/(?![/\\\\])[^\s\\\\]*$#D', $target) === 1 ? $target : '/';
    }
}
