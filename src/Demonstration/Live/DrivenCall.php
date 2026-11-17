<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

final readonly class DrivenCall
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $fields = [],
        public ?string $bearer = null,
    ) {
    }
}
