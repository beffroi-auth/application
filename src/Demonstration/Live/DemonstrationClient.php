<?php

declare(strict_types=1);

namespace App\Demonstration\Live;

final readonly class DemonstrationClient
{
    public function __construct(
        public string $id,
        public ?string $secret,
        public string $scope,
        public ?string $loginHint = null,
        public ?string $resource = null,
    ) {
    }
}
