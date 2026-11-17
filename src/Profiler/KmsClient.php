<?php

declare(strict_types=1);

namespace App\Profiler;

/**
 * The name of a KMS client under "key_management.clients", never its DSN, which may carry a credential.
 */
final readonly class KmsClient
{
    public function __construct(
        private string $name,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }
}
