<?php

declare(strict_types=1);

namespace App\Push;

use DateTimeImmutable;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use function max;

/**
 * Carries no identifier and no link to an approval: a notification that could be followed straight into an
 * approval is the attack the page where a user finds what waits for them exists to prevent.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public DateTimeImmutable $worthDeliveringUntil,
    ) {
    }

    /**
     * RFC 8030 §5.2
     *
     * @return int<0, max>
     */
    public function timeToLive(DateTimeImmutable $now): int
    {
        return max(0, $this->worthDeliveringUntil->getTimestamp() - $now->getTimestamp());
    }

    public function payload(): string
    {
        return json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ], JSON_THROW_ON_ERROR);
    }
}
