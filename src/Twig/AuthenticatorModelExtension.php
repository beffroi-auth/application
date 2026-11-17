<?php

declare(strict_types=1);

namespace App\Twig;

use Beffroi\Core\Application\Credential\AuthenticatorModels;
use Beffroi\Core\Domain\Credential\AuthenticatorModel;
use Beffroi\Core\Domain\Credential\ModelDeclaringCredentialInterface;
use Twig\Attribute\AsTwigFilter;

final readonly class AuthenticatorModelExtension
{
    public function __construct(
        private AuthenticatorModels $models,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_authenticator')]
    public function model(ModelDeclaringCredentialInterface $credential): ?AuthenticatorModel
    {
        return $this->models->of($credential);
    }
}
