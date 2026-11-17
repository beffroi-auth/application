<?php

declare(strict_types=1);

namespace App\Http\Account;

final readonly class AccountSectionState
{
    /**
     * @param array<string, string|int> $parameters
     */
    public function __construct(
        public string $key,
        public array $parameters = [],
    ) {
    }
}
