<?php

declare(strict_types=1);

namespace App\Twig;

use Beffroi\Core\Application\Credential\AuthenticatorModels;
use Beffroi\Core\Domain\Credential\AuthenticatorModel;
use Beffroi\Core\Domain\Credential\ModelDeclaringCredentialInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Attribute\AsTwigFilter;

final readonly class AuthenticatorModelExtension
{
    public function __construct(
        private AuthenticatorModels $models,
    ) {
    }

    #[AsTwigFilter(name: 'beffroi_authenticator')]
    public function model(ModelDeclaringCredentialInterface|Uuid $declared): ?AuthenticatorModel
    {
        return $declared instanceof Uuid ? $this->models->named($declared) : $this->models->of($declared);
    }
}
