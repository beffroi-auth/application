<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Seconds one call to a third party is given, as both its "timeout", the longest HttpClient waits for a byte, and
 * its "max_duration", the longest the whole exchange takes.
 */
final readonly class Timeout
{
    public const float MINIMUM = 0.1;

    public function __construct(
        public float $seconds,
    ) {
    }

    /**
     * @return array{timeout: float, max_duration: float}
     */
    public function options(): array
    {
        return [
            'timeout' => $this->seconds,
            'max_duration' => $this->seconds,
        ];
    }
}
