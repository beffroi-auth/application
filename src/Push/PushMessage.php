<?php

declare(strict_types=1);

namespace App\Push;

use DateTimeImmutable;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use function max;

/**
 * Carries no identifier and no link that acts: a notification that could be followed straight into an approval, or
 * into undoing a change, is the attack the pages where a person answers or undoes it exist to prevent.
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
