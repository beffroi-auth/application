<?php

declare(strict_types=1);

namespace App\Http;

final readonly class ReceivedAuthorizationRequest
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public array $parameters,
        public bool $pushed,
        public bool $carriedByObject,
        public ?string $requestUriHash = null,
    ) {
    }
}
