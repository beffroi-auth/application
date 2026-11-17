<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProviderScript;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class PasswordStrengthScriptController
{
    public const string ROUTE = 'beffroi_interaction_password_strength_script';

    public const string FILE = 'password-strength.js';

    public function __construct(
        private ProviderScript $scripts,
    ) {
    }

    #[Route('/password-strength.js', name: self::ROUTE, methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return $this->scripts->serve($request, self::FILE);
    }
}
