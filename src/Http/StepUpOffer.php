<?php

declare(strict_types=1);

namespace App\Http;

use function array_map;
use Beffroi\Core\Domain\Session\AuthenticationContextClass;
use Beffroi\Oidc\Domain\Interaction\StepUp;

final readonly class StepUpOffer
{
    /**
     * @param non-empty-list<string> $classes in the order the client preferred them
     * @param non-empty-list<string> $kinds each once
     */
    public function __construct(
        public array $classes,
        public array $kinds,
    ) {
    }

    public static function of(StepUp $stepUp): self
    {
        return new self(
            array_map(static fn (AuthenticationContextClass $class): string => $class->value, $stepUp->classes),
            $stepUp->kinds(),
        );
    }
}
