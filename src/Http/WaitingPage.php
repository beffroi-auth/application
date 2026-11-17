<?php

declare(strict_types=1);

namespace App\Http;

final readonly class WaitingPage
{
    /**
     * @param array<string, string> $parameters written by the provider, never what a request carried
     */
    public function __construct(
        public string $route,
        public array $parameters,
        public ?StepUpOffer $stepUp = null,
    ) {
    }
}
