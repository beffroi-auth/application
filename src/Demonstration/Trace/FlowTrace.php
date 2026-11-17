<?php

declare(strict_types=1);

namespace App\Demonstration\Trace;

use function array_key_exists;
use DateTimeImmutable;

final readonly class FlowTrace
{
    /**
     * @param list<TracedHop>       $hops
     * @param array<string, string> $holdings
     */
    public function __construct(
        public string $grantType,
        public string $clientId,
        public DateTimeImmutable $startedAt,
        public array $hops = [],
        private array $holdings = [],
    ) {
    }

    public function with(TracedHop $hop): self
    {
        return new self($this->grantType, $this->clientId, $this->startedAt, [...$this->hops, $hop], $this->holdings);
    }

    /**
     * @param array<string, string> $holdings merged into what the client held
     */
    public function holding(array $holdings): self
    {
        return new self($this->grantType, $this->clientId, $this->startedAt, $this->hops, [...$this->holdings, ...$holdings]);
    }

    public function holds(string $name): ?string
    {
        return array_key_exists($name, $this->holdings) ? $this->holdings[$name] : null;
    }

    public function drew(string $hop): bool
    {
        foreach ($this->hops as $traced) {
            if ($traced->hop === $hop) {
                return true;
            }
        }

        return false;
    }

    public function times(string $hop): int
    {
        $times = 0;
        foreach ($this->hops as $traced) {
            if ($traced->hop === $hop) {
                ++$times;
            }
        }

        return $times;
    }
}
