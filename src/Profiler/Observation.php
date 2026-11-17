<?php

declare(strict_types=1);

namespace App\Profiler;

/**
 * Never holds a credential, and names personal data rather than giving it.
 */
final readonly class Observation
{
    /**
     * @param array<string, string> $entries by label, in the order they are shown
     */
    public function __construct(
        public string $tab,
        public string $heading,
        public array $entries,
    ) {
    }

    public function isSameAs(self $other): bool
    {
        return $this->tab === $other->tab && $this->heading === $other->heading && $this->entries === $other->entries;
    }
}
